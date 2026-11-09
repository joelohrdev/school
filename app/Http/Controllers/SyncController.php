<?php

namespace App\Http\Controllers;

use App\Jobs\SyncPowerSchoolJob;
use App\Models\SyncRun;
use App\Presenters\Gradebook;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function index(Gradebook $gradebook): Response
    {
        $running = SyncRun::query()
            ->where('status', SyncRun::STATUS_RUNNING)
            ->where('started_at', '>', now()->subMinutes(15))
            ->exists();

        return Inertia::render('sync/index', [
            'runs' => SyncRun::query()->latest('started_at')->latest('id')->limit(50)->get()
                ->map(fn (SyncRun $run) => $gradebook->syncRun($run))
                ->values(),
            'busy' => $running || SyncPowerSchoolJob::isQueued(),
        ]);
    }

    public function store(): RedirectResponse
    {
        if (! SyncPowerSchoolJob::queueOnce()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'A sync is already on its way.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sync queued. This page updates when it finishes.']);

        return back();
    }
}
