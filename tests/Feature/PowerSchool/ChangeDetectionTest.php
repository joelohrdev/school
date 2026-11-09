<?php

use App\Models\Alert;
use App\Services\PowerSchool\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'powerschool.base_url' => 'https://ps.example.test',
        'powerschool.username' => 'parent@example.test',
        'powerschool.password' => 'secret',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-09 13:00:00', 'UTC'));
});

/**
 * The base fixture with Avery's record changed by $mutate.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
 * @return array<string, mixed>
 */
function averyWith(callable $mutate): array
{
    $data = powerschoolFixtureData('student_data');
    $vos = &$data['getStudentDataResponse']['return']['studentDataVOs'];
    $vos[0] = $mutate($vos[0]);

    return $data;
}

/** @param  array<string, mixed>  $changes */
function withScore(int $index, array $changes): array
{
    return averyWith(function (array $kid) use ($index, $changes) {
        $kid['assignmentScores'][$index] = array_merge($kid['assignmentScores'][$index], $changes);

        return $kid;
    });
}

/** Baseline sync, then a later sync of $data. Returns alert types raised by the second. */
function syncThen(array $data, string $later = '+2 hours'): array
{
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    test()->travelTo(now()->modify($later));
    fakePowerSchoolSync($data);
    $run = app(SyncService::class)->run()->run;

    return Alert::where('sync_run_id', $run->id)->orderBy('id')->pluck('type')->all();
}

it('treats the first sync as a baseline', function () {
    fakePowerSchoolSync();

    app(SyncService::class)->run();

    expect(Alert::count())->toBe(0);
});

it('raises nothing when nothing changed', function () {
    expect(syncThen(powerschoolFixtureData('student_data')))->toBe([]);
});

it('alerts when an assignment is newly flagged missing', function () {
    expect(syncThen(withScore(1, ['missing' => true])))->toBe([Alert::TYPE_MISSING]);

    $alert = Alert::sole();
    expect($alert->payload)->toMatchArray([
        'course_name' => 'Science 7',
        'assignment_name' => 'Cells unit test',
        'due_on' => '2026-11-05',
    ])->and($alert->student->first_name)->toBe('Avery');
});

it('records a resolved event and the posted score when missing work is turned in', function () {
    $types = syncThen(withScore(0, ['missing' => false, 'score' => '8', 'percent' => 80, 'letterGrade' => 'B']));

    expect($types)->toBe([Alert::TYPE_MISSING_RESOLVED, Alert::TYPE_SCORE_POSTED]);
});

it('alerts when an assignment is newly flagged late', function () {
    expect(syncThen(withScore(0, ['late' => true])))->toBe([Alert::TYPE_LATE]);
});

it('does not alert for exempt work', function () {
    expect(syncThen(withScore(1, ['missing' => true, 'exempt' => true])))->toBe([]);
});

it('treats an exemption as resolving missing work', function () {
    expect(syncThen(withScore(0, ['exempt' => true])))->toBe([Alert::TYPE_MISSING_RESOLVED]);
});

it('alerts for new assignments that arrive already missing or scored', function () {
    $data = averyWith(function (array $kid) {
        $kid['assignments'][] = ['id' => 7003, 'sectionid' => 401, 'categoryId' => 11, 'name' => 'Decimals quiz', 'dueDate' => '2026-11-06T00:00:00.000Z', 'pointspossible' => 20];
        $kid['assignments'][] = ['id' => 7004, 'sectionid' => 401, 'categoryId' => 11, 'name' => 'Ratios', 'dueDate' => '2026-11-07T00:00:00.000Z', 'pointspossible' => 5];
        $kid['assignmentScores'][] = ['id' => 9103, 'assignmentId' => 7003, 'missing' => true];
        $kid['assignmentScores'][] = ['id' => 9104, 'assignmentId' => 7004, 'score' => '5', 'percent' => 100];

        return $kid;
    });

    expect(syncThen($data))->toBe([Alert::TYPE_MISSING, Alert::TYPE_SCORE_POSTED]);
});

it('alerts when a class grade drops by at least the threshold', function () {
    $data = averyWith(function (array $kid) {
        $kid['finalGrades'][0]['percent'] = 71.5;

        return $kid;
    });

    expect(syncThen($data, '+1 day'))->toBe([Alert::TYPE_GRADE_DROP]);
    expect(Alert::sole()->payload)->toMatchArray([
        'course_name' => 'Math 7', 'previous_percent' => 74.5, 'percent' => 71.5, 'drop' => 3.0,
    ]);
});

it('ignores smaller drops and respects a configured threshold', function () {
    $data = averyWith(function (array $kid) {
        $kid['finalGrades'][0]['percent'] = 72.5;

        return $kid;
    });

    expect(syncThen($data, '+1 day'))->toBe([]);

    config(['powerschool.grade_drop_threshold' => 1.5]);
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['finalGrades'][0]['percent'] = 70.5;

    $this->travel(1)->days();
    fakePowerSchoolSync($data);
    app(SyncService::class)->run();

    expect(Alert::pluck('type')->all())->toBe([Alert::TYPE_GRADE_DROP]);
});

it('reports a drop once even when a later run the same day sees it again', function () {
    $data = averyWith(function (array $kid) {
        $kid['finalGrades'][0]['percent'] = 60;

        return $kid;
    });

    syncThen($data, '+1 day');
    $this->travel(8)->hours();
    app(SyncService::class)->run();

    expect(Alert::where('type', Alert::TYPE_GRADE_DROP)->count())->toBe(1);
});

it('counts would-be alerts on a dry run without saving them', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    fakePowerSchoolSync(withScore(1, ['missing' => true]));
    $result = app(SyncService::class)->run(dryRun: true);

    expect($result->counts['alerts']['created'])->toBe(1)
        ->and(Alert::count())->toBe(0);
});
