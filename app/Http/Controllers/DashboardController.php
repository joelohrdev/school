<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Presenters\Gradebook;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Gradebook $gradebook): Response
    {
        return Inertia::render('dashboard', [
            'students' => Student::orderBy('first_name')->get()->map(fn (Student $s) => $gradebook->summary($s))->values(),
            'lastSync' => $gradebook->syncRun($gradebook->lastSync()),
            'today' => $gradebook->today()->toDateString(),
        ]);
    }
}
