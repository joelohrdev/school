<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Presenters\Gradebook;
use App\Services\QuarterCalendar;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Gradebook $gradebook, QuarterCalendar $calendar): Response
    {
        $today = $gradebook->today();

        return Inertia::render('dashboard', [
            'students' => Student::orderBy('first_name')->get()
                ->map(fn (Student $s) => $gradebook->summary($s, $calendar->current($calendar->all($s), $today)))
                ->values(),
            'lastSync' => $gradebook->syncRun($gradebook->lastSync()),
            'today' => $gradebook->today()->toDateString(),
        ]);
    }
}
