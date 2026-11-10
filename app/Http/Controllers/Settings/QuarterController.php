<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\QuarterUpdateRequest;
use App\Models\SchoolQuarter;
use App\Services\QuarterCalendar;
use App\Support\Quarter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuarterController extends Controller
{
    /**
     * Show the school's quarters: PowerSchool's when it sends them, or the
     * form to enter them.
     */
    public function edit(QuarterCalendar $calendar): Response
    {
        $fromPowerSchool = $calendar->fromPowerSchool();

        return Inertia::render('settings/quarters', [
            'fromPowerSchool' => $fromPowerSchool,
            'powerSchoolQuarters' => $fromPowerSchool
                ? array_values($calendar->all()->map(fn (Quarter $q) => $q->toArray())->all())
                : [],
            'quarters' => array_values($calendar->manual()->map(fn (Quarter $q) => [
                'name' => $q->name,
                'starts_on' => $q->startsOn,
                'ends_on' => $q->endsOn,
            ])->all()),
        ]);
    }

    /**
     * Replace the hand-entered quarters.
     */
    public function update(QuarterUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            SchoolQuarter::query()->delete();

            foreach ($request->quarters() as $q) {
                SchoolQuarter::create($q);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quarters saved.')]);

        return to_route('quarters.edit');
    }
}
