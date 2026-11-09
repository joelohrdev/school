<?php

use App\Models\AttendanceRecord;
use App\Models\NotInSessionDay;
use App\Models\Section;
use App\Models\Student;
use App\Models\SyncRun;
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

it('stores attendance with its code and days off', function () {
    fakePowerSchoolSync();

    app(SyncService::class)->run();

    $absent = AttendanceRecord::where('ps_id', 6001)->sole();

    expect(AttendanceRecord::count())->toBe(3)
        ->and($absent->date->toDateString())->toBe('2026-11-04')
        ->and($absent->code)->toBe('AE')
        ->and($absent->description)->toBe('Absent Excused')
        ->and(AttendanceRecord::marked()->count())->toBe(2)
        ->and(NotInSessionDay::sole()->only('school_number', 'description'))->toBe(['school_number' => 10, 'description' => 'Thanksgiving'])
        ->and(NotInSessionDay::sole()->date->toDateString())->toBe('2026-11-26');
});

it('stays idempotent with attendance and days off', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    $second = app(SyncService::class)->run();

    expect($second->counts['attendance_records']['created'])->toBe(0)
        ->and($second->counts['not_in_session_days']['created'])->toBe(0)
        ->and(AttendanceRecord::count())->toBe(3)
        ->and(NotInSessionDay::count())->toBe(1);
});

it('skips day-off rows it cannot read a date from', function () {
    $data = powerschoolFixtureData('student_data');
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['notInSessionDays'] = [['description' => 'No date here']];
    fakePowerSchoolSync($data);

    $result = app(SyncService::class)->run();

    expect($result->counts['not_in_session_days']['skipped'])->toBe(1);
});

describe('day off detection', function () {
    beforeEach(function () {
        $avery = Student::create(['ps_id' => 1, 'first_name' => 'A', 'last_name' => 'E']);
        $blake = Student::create(['ps_id' => 2, 'first_name' => 'B', 'last_name' => 'E']);
        Section::create(['student_id' => $avery->id, 'ps_id' => 1, 'course_name' => 'Math', 'school_number' => 10]);
        Section::create(['student_id' => $blake->id, 'ps_id' => 2, 'course_name' => 'Reading', 'school_number' => 20]);
        $this->day = CarbonImmutable::parse('2026-11-26');
    });

    it('is a day off when the whole district is out', function () {
        NotInSessionDay::create(['school_number' => 0, 'date' => '2026-11-26']);

        expect(NotInSessionDay::isDayOff($this->day))->toBeTrue();
    });

    it('is not a day off while one kid still has school', function () {
        NotInSessionDay::create(['school_number' => 10, 'date' => '2026-11-26']);

        expect(NotInSessionDay::isDayOff($this->day))->toBeFalse();
    });

    it('is a day off when every kid is out', function () {
        NotInSessionDay::create(['school_number' => 10, 'date' => '2026-11-26']);
        NotInSessionDay::create(['school_number' => 20, 'date' => '2026-11-26']);

        expect(NotInSessionDay::isDayOff($this->day))->toBeTrue()
            ->and(NotInSessionDay::isDayOff($this->day->addDay()))->toBeFalse();
    });
});

it('skips scheduled runs on a day off but not manual ones', function () {
    NotInSessionDay::create(['school_number' => 0, 'date' => '2026-11-26']);
    $this->travelTo(CarbonImmutable::parse('2026-11-26 12:30:00', 'UTC'));
    fakePowerSchoolSync();

    $this->artisan('powerschool:sync --trigger=schedule')
        ->expectsOutputToContain('No school today')
        ->assertSuccessful();

    expect(SyncRun::count())->toBe(0);

    $this->artisan('powerschool:sync')->assertSuccessful();

    expect(SyncRun::count())->toBe(1);
});
