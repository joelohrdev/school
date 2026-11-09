<?php

namespace App\Services\PowerSchool;

use App\Models\Assignment;
use App\Models\AssignmentCategory;
use App\Models\AssignmentScore;
use App\Models\AttendanceRecord;
use App\Models\FinalGrade;
use App\Models\GradeSnapshot;
use App\Models\NotInSessionDay;
use App\Models\RawPayload;
use App\Models\ReportingTerm;
use App\Models\Section;
use App\Models\Student;
use App\Models\SyncRun;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\PowerSchool\Exceptions\AuthFailed;
use App\Services\PowerSchool\Exceptions\PowerSchoolException;
use App\Services\SyncHealth\FailureNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pulls every kid's data from PowerSchool and upserts it, keyed on
 * PowerSchool ids, so running it twice changes nothing the second time.
 *
 * Each student is written in its own transaction: one bad record fails that
 * student, not the whole run. A dry run does all the same work inside an
 * outer transaction and rolls it back.
 */
class SyncService
{
    public const LOCK = 'powerschool:sync';

    public function __construct(
        private readonly PowerSchoolClient $client,
        private readonly CurrentGradeResolver $grades,
        private readonly ChangeDetector $changes,
        private readonly FailureNotifier $failures,
    ) {}

    public function run(bool $dryRun = false, string $trigger = SyncRun::TRIGGER_CLI): SyncResult
    {
        $lock = Cache::lock(self::LOCK, 15 * 60);

        if (! $lock->get()) {
            return SyncResult::skipped('Another sync is already running.');
        }

        try {
            if (! $dryRun) {
                return $this->execute($trigger, dryRun: false);
            }

            DB::beginTransaction();

            try {
                return $this->execute($trigger, dryRun: true);
            } finally {
                DB::rollBack();
            }
        } finally {
            $lock->release();
        }
    }

    private function execute(string $trigger, bool $dryRun): SyncResult
    {
        $now = CarbonImmutable::now();

        $run = SyncRun::create([
            'status' => SyncRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'dry_run' => $dryRun,
            'started_at' => $now,
        ]);

        $counts = new SyncCounts;
        $errors = [];

        try {
            $records = $this->client->getStudentData($this->login());
        } catch (PowerSchoolException $e) {
            return $this->finish($run, $counts, [class_basename($e).': '.$e->getMessage()], $dryRun);
        }

        foreach ($records as $record) {
            $studentCounts = new SyncCounts;

            try {
                DB::transaction(fn () => $this->syncStudent($record, $run, $studentCounts, $now));
                $counts->merge($studentCounts);
            } catch (Throwable $e) {
                $errors[] = 'Student '.(Value::int($record['student']['id'] ?? null) ?? '?')
                    .': '.class_basename($e).': '.$e->getMessage();
            }
        }

        return $this->finish($run, $counts, $errors, $dryRun);
    }

    private function login(): UserSession
    {
        $username = (string) config('powerschool.username');
        $password = (string) config('powerschool.password');

        if ($username === '' || $password === '') {
            throw new AuthFailed('POWERSCHOOL_USERNAME and POWERSCHOOL_PASSWORD must be set.');
        }

        return $this->client->login($username, $password);
    }

    /**
     * @param  list<string>  $errors
     */
    private function finish(SyncRun $run, SyncCounts $counts, array $errors, bool $dryRun): SyncResult
    {
        $status = $errors === [] ? SyncRun::STATUS_SUCCEEDED : SyncRun::STATUS_FAILED;

        $run->update([
            'status' => $status,
            'finished_at' => CarbonImmutable::now(),
            'counts' => $counts->toArray(),
            'error' => $errors === [] ? null : implode("\n", $errors),
        ]);

        if (! $dryRun) {
            try {
                $this->failures->check($run);
            } catch (Throwable $e) {
                // A mail outage must not turn a finished sync into a crash.
                report($e);
            }
        }

        return new SyncResult($status, $dryRun, $counts->toArray(), $errors, $dryRun ? null : $run);
    }

    /**
     * @param  array<string, mixed>  $vo  one normalized studentDataVO
     */
    private function syncStudent(array $vo, SyncRun $run, SyncCounts $counts, CarbonImmutable $now): void
    {
        $s = $vo['student'];

        $student = Student::updateOrCreate(
            ['ps_id' => Value::int($s['id'] ?? null) ?? throw new \UnexpectedValueException('student has no id')],
            [
                'ps_dcid' => Value::int($s['dcid'] ?? null),
                'first_name' => Value::str($s['firstName'] ?? null) ?? '',
                'middle_name' => Value::str($s['middleName'] ?? null),
                'last_name' => Value::str($s['lastName'] ?? null) ?? '',
                'grade_level' => Value::int($s['gradeLevel'] ?? null),
                'current_term' => Value::str($s['currentTerm'] ?? null),
                'current_gpa' => Value::str($s['currentGPA'] ?? null),
            ],
        );
        $counts->record('students', $student);

        // A kid's first sync is the baseline: existing history raises no alerts.
        $baseline = $student->wasRecentlyCreated;

        $teachers = $this->upsert(Teacher::class, 'teachers', $vo['teachers'] ?? [], [], $counts, fn (array $t) => [
            'first_name' => Value::str($t['firstName'] ?? null),
            'last_name' => Value::str($t['lastName'] ?? null),
            'email' => Value::str($t['email'] ?? null),
            'school_phone' => Value::str($t['schoolPhone'] ?? null),
        ]);

        $categories = $this->upsert(AssignmentCategory::class, 'assignment_categories', $vo['assignmentCategories'] ?? [], [], $counts, fn (array $c) => [
            'name' => Value::str($c['name'] ?? null) ?? 'Uncategorized',
            'abbreviation' => Value::str($c['abbreviation'] ?? null),
            'description' => Value::str($c['description'] ?? null),
        ]);

        // Field names for `terms` are a guess modeled on reportingTerms; only `id` is confirmed.
        $terms = $this->upsert(Term::class, 'terms', $vo['terms'] ?? [], [], $counts, fn (array $t) => [
            'name' => Value::str($t['name'] ?? $t['title'] ?? null),
            'abbreviation' => Value::str($t['abbreviation'] ?? null),
            'starts_on' => Value::date($t['startDate'] ?? null),
            'ends_on' => Value::date($t['endDate'] ?? null),
        ]);

        $reportingTerms = $this->upsert(ReportingTerm::class, 'reporting_terms', $vo['reportingTerms'] ?? [], [], $counts, fn (array $r) => [
            'ps_term_id' => Value::int($r['termid'] ?? null),
            'term_id' => $terms[Value::int($r['termid'] ?? null)] ?? null,
            'abbreviation' => Value::str($r['abbreviation'] ?? null),
            'title' => Value::str($r['title'] ?? null),
            'starts_on' => Value::date($r['startDate'] ?? null),
            'ends_on' => Value::date($r['endDate'] ?? null),
            'sort_order' => Value::int($r['sortOrder'] ?? null),
            'suppress_grades' => Value::bool($r['suppressGrades'] ?? null) ?? false,
            'suppress_percents' => Value::bool($r['suppressPercents'] ?? null) ?? false,
        ]);

        $sections = $this->upsert(Section::class, 'sections', $vo['sections'] ?? [], ['student_id' => $student->id], $counts, fn (array $sec) => [
            'teacher_id' => $teachers[Value::int($sec['teacherID'] ?? null)] ?? null,
            'term_id' => $terms[Value::int($sec['termID'] ?? null)] ?? null,
            'ps_dcid' => Value::int($sec['dcid'] ?? null),
            'ps_term_id' => Value::int($sec['termID'] ?? null),
            'course_name' => Value::str($sec['schoolCourseTitle'] ?? null) ?? Value::str($sec['courseCode'] ?? null) ?? 'Untitled class',
            'course_code' => Value::str($sec['courseCode'] ?? null),
            'section_number' => Value::str($sec['sectionNum'] ?? null),
            'period' => Value::str($sec['expression'] ?? null),
            'period_sort' => Value::int($sec['periodSort'] ?? null),
            'room' => Value::str($sec['roomName'] ?? null),
            'school_number' => Value::int($sec['schoolNumber'] ?? null),
        ]);

        $assignments = [];

        foreach ($vo['assignments'] ?? [] as $a) {
            $sectionId = $sections[Value::int($a['sectionid'] ?? null)] ?? null;
            $psId = Value::int($a['id'] ?? null);

            if ($sectionId === null || $psId === null) {
                $counts->skip('assignments');

                continue;
            }

            $assignment = Assignment::updateOrCreate(['section_id' => $sectionId, 'ps_id' => $psId], [
                'assignment_category_id' => $categories[Value::int($a['categoryId'] ?? null)] ?? null,
                'ps_assignment_id' => Value::int($a['assignmentid'] ?? null),
                'name' => Value::str($a['name'] ?? null) ?? Value::str($a['abbreviation'] ?? null) ?? 'Untitled assignment',
                'description' => Value::str($a['description'] ?? null),
                'due_on' => Value::date($a['dueDate'] ?? null),
                'points_possible' => Value::decimal($a['pointspossible'] ?? null),
                'weight' => Value::decimal($a['weight'] ?? null),
                'counts_in_final_grade' => Value::bool($a['includeinfinalgrades'] ?? null) ?? true,
                'scores_published' => Value::bool($a['publishscores'] ?? null) ?? true,
            ]);
            $counts->record('assignments', $assignment);
            $assignments[$psId] = $assignment->id;
        }

        foreach ($vo['assignmentScores'] ?? [] as $sc) {
            $assignmentId = $assignments[Value::int($sc['assignmentId'] ?? null)] ?? null;
            $psId = Value::int($sc['id'] ?? null);

            if ($assignmentId === null || $psId === null) {
                $counts->skip('assignment_scores');

                continue;
            }

            $score = AssignmentScore::firstOrNew(['student_id' => $student->id, 'ps_id' => $psId]);
            $before = $this->changes->scoreState($score);
            $score->fill([
                'assignment_id' => $assignmentId,
                'score' => Value::str($sc['score'] ?? null),
                'percent' => Value::decimal($sc['percent'] ?? null),
                'letter_grade' => Value::str($sc['letterGrade'] ?? null),
                'missing' => Value::bool($sc['missing'] ?? null) ?? false,
                'late' => Value::bool($sc['late'] ?? null) ?? false,
                'exempt' => Value::bool($sc['exempt'] ?? null) ?? false,
                'collected' => Value::bool($sc['collected'] ?? null) ?? false,
                'incomplete' => Value::bool($sc['incomplete'] ?? null) ?? false,
                'comment' => Value::str($sc['comment'] ?? null),
            ]);

            $hasScore = $score->score !== null || $score->percent !== null || $score->letter_grade !== null;
            $score->scored_at = $hasScore ? ($score->scored_at ?? $now) : null;
            $score->save();

            $counts->record('assignment_scores', $score);

            foreach ($this->changes->scoreChanged($student, $score, $before, $run, $baseline) as $alert) {
                $counts->record('alerts', $alert);
            }
        }

        foreach ($vo['finalGrades'] ?? [] as $fg) {
            $sectionId = $sections[Value::int($fg['sectionid'] ?? null)] ?? null;
            $psId = Value::int($fg['id'] ?? null);

            if ($sectionId === null || $psId === null) {
                $counts->skip('final_grades');

                continue;
            }

            $grade = FinalGrade::updateOrCreate(['student_id' => $student->id, 'ps_id' => $psId], [
                'section_id' => $sectionId,
                'reporting_term_id' => $reportingTerms[Value::int($fg['reportingTermId'] ?? null)] ?? null,
                'percent' => Value::decimal($fg['percent'] ?? null),
                'letter_grade' => Value::str($fg['grade'] ?? null),
                'as_of' => Value::date($fg['dateStored'] ?? null),
                'store_type' => Value::str($fg['storeType'] ?? null),
                'comment' => Value::str($fg['commentValue'] ?? null),
            ]);
            $counts->record('final_grades', $grade);
        }

        $this->syncDaysOff($vo['notInSessionDays'] ?? [], $counts);
        $this->syncAttendance($student, $vo['attendance'] ?? [], $vo['attendanceCodes'] ?? [], $run, $counts, $baseline);

        $this->snapshot($student, $run, $counts, $now, $baseline);

        RawPayload::create(['student_id' => $student->id, 'sync_run_id' => $run->id, 'payload' => $vo]);
    }

    /**
     * Field names for notInSessionDays are unconfirmed, so several likely
     * spellings are tried and rows without a readable date are skipped.
     *
     * @param  list<mixed>  $rows
     */
    private function syncDaysOff(array $rows, SyncCounts $counts): void
    {
        foreach ($rows as $row) {
            $date = is_array($row) ? Value::date(self::first($row, ['calendarDay', 'date', 'calendarDate', 'day'])) : null;

            if ($date === null) {
                $counts->skip('not_in_session_days');

                continue;
            }

            $school = Value::int(self::first($row, ['schoolNumber', 'schoolid', 'schoolId', 'school_number'])) ?? 0;

            $day = NotInSessionDay::query()->where('school_number', $school)->whereDate('date', $date)->first()
                ?? new NotInSessionDay(['school_number' => $school, 'date' => $date]);

            $day->fill(['description' => Value::str(self::first($row, ['description', 'name', 'note', 'type']))])->save();
            $counts->record('not_in_session_days', $day);
        }
    }

    /**
     * Field names for attendance and attendanceCodes are unconfirmed; the
     * names below follow PowerSchool's own column names.
     *
     * @param  list<mixed>  $rows
     * @param  list<mixed>  $codeRows
     */
    private function syncAttendance(Student $student, array $rows, array $codeRows, SyncRun $run, SyncCounts $counts, bool $baseline): void
    {
        $codes = [];

        foreach ($codeRows as $c) {
            if (is_array($c) && ($id = Value::int($c['id'] ?? null)) !== null) {
                $codes[$id] = [
                    'code' => Value::str(self::first($c, ['attCode', 'code'])),
                    'description' => Value::str(self::first($c, ['description', 'name'])),
                ];
            }
        }

        foreach ($rows as $row) {
            $psId = is_array($row) ? Value::int($row['id'] ?? null) : null;
            $date = is_array($row) ? Value::date(self::first($row, ['attDate', 'date', 'attendanceDate'])) : null;

            if ($psId === null || $date === null) {
                $counts->skip('attendance_records');

                continue;
            }

            $code = $codes[Value::int(self::first($row, ['attCodeid', 'attCodeId', 'attendanceCodeId']))] ?? ['code' => null, 'description' => null];

            $record = AttendanceRecord::updateOrCreate(['student_id' => $student->id, 'ps_id' => $psId], [
                'date' => $date,
                'code' => $code['code'],
                'description' => $code['description'],
                'ps_period_id' => Value::int(self::first($row, ['periodid', 'periodId'])),
            ]);
            $counts->record('attendance_records', $record);

            if ($alert = $this->changes->attendanceChanged($student, $record, $run, $baseline)) {
                $counts->record('alerts', $alert);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function first(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (($row[$key] ?? null) !== null) {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * One snapshot per section per (district-local) day; a later sync the
     * same day overwrites it.
     */
    private function snapshot(Student $student, SyncRun $run, SyncCounts $counts, CarbonImmutable $now, bool $baseline): void
    {
        $today = $now->setTimezone((string) config('powerschool.timezone'));

        $student->sections()->with('finalGrades.reportingTerm')->get()->each(function (Section $section) use ($student, $run, $counts, $today, $baseline) {
            $current = $this->grades->pick($section->finalGrades, $today);

            if ($current === null) {
                return;
            }

            $previous = GradeSnapshot::query()
                ->where('section_id', $section->id)
                ->whereDate('snapshot_on', '<=', $today->toDateString())
                ->latest('snapshot_on')
                ->first();

            if ($alert = $this->changes->gradeChanged($student, $section, $previous, $current, $run, $baseline)) {
                $counts->record('alerts', $alert);
            }

            $snapshot = $previous?->snapshot_on->isSameDay($today)
                ? $previous
                : new GradeSnapshot(['section_id' => $section->id, 'snapshot_on' => $today->toDateString()]);

            $snapshot->fill([
                'student_id' => $student->id,
                'percent' => $current->percent,
                'letter_grade' => $current->letter_grade,
            ])->save();

            $counts->record('grade_snapshots', $snapshot);
        });
    }

    /**
     * Upsert rows keyed on (scope + ps_id) and return [ps_id => local id].
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<mixed>  $rows  items are arrays unless the payload is malformed
     * @param  array<string, mixed>  $scope
     * @param  callable(array<string, mixed>): array<string, mixed>  $attributes
     * @return array<int, int>
     */
    private function upsert(string $model, string $entity, array $rows, array $scope, SyncCounts $counts, callable $attributes): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $psId = is_array($row) ? Value::int($row['id'] ?? null) : null;

            if ($psId === null) {
                $counts->skip($entity);

                continue;
            }

            $record = $model::updateOrCreate($scope + ['ps_id' => $psId], $attributes($row));
            $counts->record($entity, $record);
            $ids[$psId] = $record->getKey();
        }

        return $ids;
    }
}
