<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Presenters\Gradebook;
use App\Services\QuarterCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function show(Request $request, Student $student, Gradebook $gradebook, QuarterCalendar $calendar): Response
    {
        $today = $gradebook->today();
        $quarters = $calendar->all($student);
        $quarter = $calendar->select($quarters, $request->string('quarter')->toString() ?: null, $today);

        return Inertia::render('students/show', [
            'student' => $gradebook->student($student),
            'quarters' => $calendar->picker($quarters, $quarter, $today),
            'classes' => $gradebook->classes($student, $quarter),
            'assignments' => $gradebook->assignments($student, quarter: $quarter),
            'attendance' => $gradebook->attendance($student, $quarter),
            'history' => $gradebook->history($student, quarter: $quarter),
            'today' => $today->toDateString(),
        ]);
    }
}
