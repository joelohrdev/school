<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Assignment;
use App\Models\AssignmentCategory;
use App\Models\AssignmentScore;
use App\Models\AttendanceRecord;
use App\Models\FinalGrade;
use App\Models\GradeSnapshot;
use App\Models\ReportingTerm;
use App\Models\Section;
use App\Models\Student;
use App\Models\SyncRun;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Made-up kids and grades for previewing the UI without a PowerSchool
 * login. Never run it against a database holding real data:
 *
 *   DB_DATABASE=/tmp/kids-demo.sqlite php artisan migrate --seed --seeder=DemoSeeder
 *
 * Signs in as demo@example.test / demo-password.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || Student::query()->exists()) {
            throw new RuntimeException('DemoSeeder only runs on an empty, non-production database.');
        }

        mt_srand(118);

        $today = CarbonImmutable::now((string) config('powerschool.timezone'))->startOfDay();

        User::factory()->create(['name' => 'Demo Parent', 'email' => 'demo@example.test', 'password' => 'demo-password']);

        $term = ReportingTerm::create([
            'ps_id' => 77, 'abbreviation' => 'Q2', 'title' => 'Quarter 2',
            'starts_on' => $today->subDays(25), 'ends_on' => $today->addDays(40), 'sort_order' => 2,
        ]);

        $categories = collect(['Homework', 'Quizzes', 'Tests', 'Projects'])
            ->mapWithKeys(fn (string $name, int $i) => [$name => AssignmentCategory::create(['ps_id' => 10 + $i, 'name' => $name])]);

        $kids = [
            ['Avery', 7, [
                ['Math 7', '1(A)', 'Rivera', 76, -0.25],
                ['Science 7', '2(A)', 'Okafor', 88, 0.05],
                ['English 7', '3(A)', 'Lindqvist', 91, 0.0],
                ['Social Studies 7', '4(A)', 'Haddad', 82, 0.15],
                ['Spanish I', '6(A)', 'Moreno', 94, 0.0],
            ]],
            ['Blake', 4, [
                ['Reading', '1(A)', 'Chen', 86, 0.1],
                ['Math', '2(A)', 'Chen', 90, -0.05],
                ['Science', '3(A)', 'Patel', 95, 0.0],
            ]],
        ];

        $teacherId = 300;
        $ids = 1000;

        foreach ($kids as $k => [$name, $grade, $classes]) {
            $student = Student::create(['ps_id' => 1001 + $k, 'first_name' => $name, 'last_name' => 'Example', 'grade_level' => $grade]);

            foreach ($classes as $c => [$course, $period, $teacherName, $end, $slope]) {
                $teacher = Teacher::firstOrCreate(['last_name' => $teacherName], [
                    'ps_id' => ++$teacherId, 'first_name' => 'Pat', 'email' => strtolower($teacherName).'@example.test',
                ]);

                $section = Section::create([
                    'student_id' => $student->id, 'teacher_id' => $teacher->id, 'ps_id' => 400 + $k * 10 + $c,
                    'course_name' => $course, 'period' => $period, 'period_sort' => $c + 1, 'room' => (string) (100 + $c * 7), 'school_number' => 10 + $k,
                ]);

                // 30 days of school-day snapshots drifting towards today's grade.
                for ($d = 30; $d >= 0; $d--) {
                    $day = $today->subDays($d);

                    if ($day->isWeekend()) {
                        continue;
                    }

                    GradeSnapshot::create([
                        'student_id' => $student->id, 'section_id' => $section->id, 'snapshot_on' => $day->toDateString(),
                        'percent' => round(min(100, $end - $slope * $d + (mt_rand(-10, 10) / 10)), 1),
                    ]);
                }

                $percent = (float) GradeSnapshot::where('section_id', $section->id)->latest('snapshot_on')->value('percent');

                FinalGrade::create([
                    'student_id' => $student->id, 'section_id' => $section->id, 'reporting_term_id' => $term->id, 'ps_id' => ++$ids,
                    'percent' => $percent, 'letter_grade' => $this->letter($percent), 'as_of' => $today,
                ]);

                foreach (range(1, 6) as $n) {
                    $due = $today->subDays(24 - $n * 4);
                    $category = $categories->values()[$n % 4];
                    $possible = $category->name === 'Tests' ? 50 : ($category->name === 'Homework' ? 10 : 20);

                    $assignment = Assignment::create([
                        'section_id' => $section->id, 'assignment_category_id' => $category->id, 'ps_id' => ++$ids,
                        'name' => $this->assignmentName($course, $category->name, $n), 'due_on' => $due, 'points_possible' => $possible,
                    ]);

                    $missing = ($k === 0 && in_array([$c, $n], [[0, 3], [0, 5], [3, 4]], true)) || ($k === 1 && [$c, $n] === [1, 5]);
                    $future = $due->greaterThan($today);
                    $pct = $missing || $future ? null : max(55, min(100, $percent + mt_rand(-12, 8)));

                    AssignmentScore::create([
                        'student_id' => $student->id, 'assignment_id' => $assignment->id, 'ps_id' => ++$ids,
                        'score' => $pct === null ? null : (string) round($possible * $pct / 100),
                        'percent' => $pct, 'letter_grade' => $pct === null ? null : $this->letter($pct),
                        'missing' => $missing, 'late' => $k === 0 && [$c, $n] === [1, 2],
                        'scored_at' => $pct === null ? null : $due->addDays(2)->setTime(15, 45),
                    ]);
                }
            }

            foreach ([[12, 'AE', 'Absent Excused'], [5, 'T', 'Tardy']] as $i => [$ago, $code, $desc]) {
                AttendanceRecord::create(['student_id' => $student->id, 'ps_id' => ++$ids, 'date' => $today->subDays($ago + $k), 'code' => $code, 'description' => $desc]);
            }
        }

        $avery = Student::where('first_name', 'Avery')->sole();
        $math = Section::where('student_id', $avery->id)->where('course_name', 'Math 7')->sole();
        $run = SyncRun::create([
            'status' => SyncRun::STATUS_SUCCEEDED, 'trigger' => SyncRun::TRIGGER_SCHEDULE,
            'started_at' => now()->subHours(2), 'finished_at' => now()->subHours(2)->addSeconds(4),
            'counts' => ['assignment_scores' => ['created' => 2, 'updated' => 3, 'unchanged' => 40, 'skipped' => 0], 'alerts' => ['created' => 3, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0]],
        ]);
        SyncRun::create([
            'status' => SyncRun::STATUS_FAILED, 'trigger' => SyncRun::TRIGGER_MANUAL,
            'started_at' => now()->subDay(), 'finished_at' => now()->subDay()->addSeconds(1),
            'counts' => [], 'error' => 'EndpointUnavailable: PowerSchool answered HTTP 503 on loginToPublicPortal.',
        ]);

        $missingMath = Assignment::where('section_id', $math->id)->whereHas('score', fn ($q) => $q->where('missing', true))->latest('due_on')->first();

        Alert::create(['student_id' => $avery->id, 'sync_run_id' => $run->id, 'type' => Alert::TYPE_MISSING, 'payload' => [
            'section_id' => $math->id, 'course_name' => 'Math 7', 'assignment_id' => $missingMath->id,
            'assignment_name' => $missingMath->name, 'due_on' => $missingMath->due_on->toDateString(),
        ]]);
        Alert::create(['student_id' => $avery->id, 'sync_run_id' => $run->id, 'type' => Alert::TYPE_GRADE_DROP, 'payload' => [
            'section_id' => $math->id, 'course_name' => 'Math 7', 'previous_percent' => 79.5, 'percent' => (float) $math->finalGrades()->value('percent'), 'letter_grade' => 'C', 'drop' => 3.2,
        ]]);
        Alert::create(['student_id' => $avery->id, 'sync_run_id' => $run->id, 'type' => Alert::TYPE_SCORE_POSTED, 'read_at' => now(), 'payload' => [
            'section_id' => $math->id, 'course_name' => 'Science 7', 'assignment_name' => 'Cells unit test', 'score' => '44', 'points_possible' => 50, 'percent' => 88,
        ]]);
    }

    private function letter(float $percent): string
    {
        return match (true) {
            $percent >= 90 => 'A',
            $percent >= 80 => 'B',
            $percent >= 70 => 'C',
            $percent >= 60 => 'D',
            default => 'F',
        };
    }

    private function assignmentName(string $course, string $category, int $n): string
    {
        return match ($category) {
            'Homework' => "{$course} homework {$n}",
            'Quizzes' => "Quiz {$n}",
            'Tests' => "Unit {$n} test",
            default => "Project {$n}",
        };
    }
}
