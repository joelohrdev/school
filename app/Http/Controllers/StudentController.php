<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Presenters\Gradebook;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function show(Student $student, Gradebook $gradebook): Response
    {
        return Inertia::render('students/show', [
            'student' => $gradebook->student($student),
            'classes' => $gradebook->classes($student),
            'assignments' => $gradebook->assignments($student),
            'attendance' => $gradebook->attendance($student),
            'today' => $gradebook->today()->toDateString(),
        ]);
    }
}
