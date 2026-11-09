<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('classes/{section}', [SectionController::class, 'show'])->name('sections.show');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('alerts/{alert}/read', [AlertController::class, 'read'])->name('alerts.read');
    Route::post('alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.read-all');

    Route::get('sync', [SyncController::class, 'index'])->name('sync.index');
    Route::post('sync', [SyncController::class, 'store'])->middleware('throttle:6,1')->name('sync.store');
});

require __DIR__.'/settings.php';
