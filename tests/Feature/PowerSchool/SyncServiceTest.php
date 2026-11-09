<?php

use App\Models\Assignment;
use App\Models\AssignmentCategory;
use App\Models\AssignmentScore;
use App\Models\FinalGrade;
use App\Models\GradeSnapshot;
use App\Models\RawPayload;
use App\Models\ReportingTerm;
use App\Models\Section;
use App\Models\Student;
use App\Models\SyncRun;
use App\Models\Teacher;
use App\Services\PowerSchool\SyncResult;
use App\Services\PowerSchool\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'powerschool.base_url' => 'https://ps.example.test',
        'powerschool.username' => 'parent@example.test',
        'powerschool.password' => 'secret',
    ]);

    // Mid-quarter (Q2 runs Oct 20 to Dec 19 in the fixture), 7am in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-11-09 13:00:00', 'UTC'));
});

function sync(bool $dryRun = false): SyncResult
{
    return app(SyncService::class)->run(dryRun: $dryRun);
}

/** @return array<string, int> */
function tableCounts(): array
{
    return collect([
        Student::class, Teacher::class, AssignmentCategory::class, ReportingTerm::class, Section::class,
        Assignment::class, AssignmentScore::class, FinalGrade::class, GradeSnapshot::class,
    ])->mapWithKeys(fn (string $m) => [class_basename($m) => $m::count()])->all();
}

it('imports every kid and their records', function () {
    fakePowerSchoolSync();

    $result = sync();

    expect($result->succeeded())->toBeTrue()
        ->and($result->errors)->toBe([])
        ->and(tableCounts())->toBe([
            'Student' => 2, 'Teacher' => 2, 'AssignmentCategory' => 2, 'ReportingTerm' => 1, 'Section' => 2,
            'Assignment' => 2, 'AssignmentScore' => 2, 'FinalGrade' => 2, 'GradeSnapshot' => 2,
        ]);

    $math = Section::where('ps_id', 401)->first();
    expect($math->course_name)->toBe('Math 7')
        ->and($math->period)->toBe('2(A)')
        ->and($math->room)->toBe('112')
        ->and($math->teacher->email)->toBe('pat.teacher@example.test');

    $worksheet = Assignment::where('ps_id', 7001)->first();
    expect($worksheet->due_on->toDateString())->toBe('2026-11-03')
        ->and($worksheet->category->name)->toBe('Homework')
        ->and((float) $worksheet->points_possible)->toBe(10.0);

    $missing = $worksheet->score;
    expect($missing->missing)->toBeTrue()
        ->and($missing->score)->toBeNull()
        ->and($missing->scored_at)->toBeNull();

    $test = AssignmentScore::where('ps_id', 9102)->first();
    expect($test->score)->toBe('42')
        ->and((float) $test->percent)->toBe(84.0)
        ->and($test->late)->toBeTrue()
        ->and($test->scored_at->equalTo(now()))->toBeTrue();
});

it('records the run with counts', function () {
    fakePowerSchoolSync();

    $run = sync()->run;

    expect($run->status)->toBe(SyncRun::STATUS_SUCCEEDED)
        ->and($run->trigger)->toBe(SyncRun::TRIGGER_CLI)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->counts['students'])->toBe(['created' => 2, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0])
        ->and($run->counts['assignment_scores']['created'])->toBe(2);
});

it('is idempotent: a second run creates and changes nothing', function () {
    fakePowerSchoolSync();

    sync();
    $before = tableCounts();
    $second = sync();

    expect(tableCounts())->toBe($before);

    foreach ($second->counts as $entity => $c) {
        expect($c['created'])->toBe(0, "{$entity} created rows")
            ->and($c['updated'])->toBe(0, "{$entity} updated rows");
    }
});

it('updates rows in place when PowerSchool changes them', function () {
    fakePowerSchoolSync();
    sync();

    $data = powerschoolFixtureData('student_data');
    $kid = &$data['getStudentDataResponse']['return']['studentDataVOs'][0];
    $kid['assignmentScores'][0] = array_merge($kid['assignmentScores'][0], ['missing' => false, 'score' => '9', 'percent' => 90, 'letterGrade' => 'A']);
    $kid['finalGrades'][0]['percent'] = 71;
    unset($kid);

    $this->travel(8)->hours();
    fakePowerSchoolSync($data);
    $result = sync();

    $score = AssignmentScore::where('ps_id', 9101)->first();

    expect(AssignmentScore::count())->toBe(2)
        ->and($score->missing)->toBeFalse()
        ->and($score->score)->toBe('9')
        ->and($score->scored_at->equalTo(now()))->toBeTrue()
        ->and($result->counts['assignment_scores']['updated'])->toBe(1)
        ->and((float) GradeSnapshot::where('section_id', Section::where('ps_id', 401)->value('id'))->sole()->percent)->toBe(71.0);
});

it('keeps the first scored_at when a score is unchanged', function () {
    fakePowerSchoolSync();
    sync();
    $first = AssignmentScore::where('ps_id', 9102)->sole()->scored_at;

    $this->travel(1)->days();
    sync();

    expect(AssignmentScore::where('ps_id', 9102)->sole()->scored_at->equalTo($first))->toBeTrue();
});

it('adds a new snapshot each day and overwrites within a day', function () {
    fakePowerSchoolSync();

    sync();
    $this->travel(8)->hours();
    sync();

    expect(GradeSnapshot::count())->toBe(2);

    $this->travel(1)->days();
    sync();

    expect(GradeSnapshot::count())->toBe(4);
});

it('stores one encrypted raw payload per kid per run', function () {
    fakePowerSchoolSync();

    $run = sync()->run;

    expect(RawPayload::where('sync_run_id', $run->id)->count())->toBe(2)
        ->and(DB::table('raw_payloads')->pluck('payload')->implode(''))->not->toContain('Avery');
});

it('writes nothing on a dry run but still reports counts', function () {
    fakePowerSchoolSync();

    $result = sync(dryRun: true);

    expect($result->succeeded())->toBeTrue()
        ->and($result->dryRun)->toBeTrue()
        ->and($result->run)->toBeNull()
        ->and($result->counts['students']['created'])->toBe(2)
        ->and(array_sum(tableCounts()))->toBe(0)
        ->and(SyncRun::count())->toBe(0)
        ->and(RawPayload::count())->toBe(0);
});

it('fails the run on bad credentials without touching data', function () {
    fakePowerSchool(['loginToPublicPortal' => 'login_failure']);

    $result = sync();

    expect($result->status)->toBe(SyncRun::STATUS_FAILED)
        ->and($result->run->error)->toBe('AuthFailed: Invalid Username or Password!')
        ->and(Student::count())->toBe(0);
});

it('fails before calling PowerSchool when credentials are not configured', function () {
    config(['powerschool.username' => null]);

    $result = sync();

    expect($result->status)->toBe(SyncRun::STATUS_FAILED)
        ->and($result->errors[0])->toContain('POWERSCHOOL_USERNAME');

    Http::assertNothingSent();
});

it('rolls back only the kid whose record is broken', function () {
    $data = powerschoolFixtureData('student_data');
    $data['getStudentDataResponse']['return']['studentDataVOs'][1]['student']['id'] = null;
    fakePowerSchoolSync($data);

    $result = sync();

    expect($result->status)->toBe(SyncRun::STATUS_FAILED)
        ->and($result->errors)->toHaveCount(1)
        ->and(Student::pluck('ps_id')->all())->toBe([1001])
        ->and(Section::count())->toBe(2);
});

it('skips rows that point at unknown parents', function () {
    $data = powerschoolFixtureData('student_data');
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['assignments'][0]['sectionid'] = 999;
    fakePowerSchoolSync($data);

    $result = sync();

    expect($result->succeeded())->toBeTrue()
        ->and($result->counts['assignments']['skipped'])->toBe(1)
        ->and($result->counts['assignment_scores']['skipped'])->toBe(1)
        ->and(Assignment::count())->toBe(1);
});

it('skips when another sync holds the lock', function () {
    $lock = Cache::lock(SyncService::LOCK, 60);
    $lock->get();

    $result = sync();
    $lock->release();

    expect($result->status)->toBe(SyncRun::STATUS_SKIPPED)
        ->and(SyncRun::count())->toBe(0);

    Http::assertNothingSent();
});
