<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $alerts = Alert::query()
            ->with('student')
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Alert $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'student' => ['id' => $a->student_id, 'name' => $a->student->first_name],
                'payload' => $a->payload,
                'createdAt' => $a->created_at?->toIso8601String(),
                'readAt' => $a->read_at?->toIso8601String(),
            ]);

        return Inertia::render('alerts/index', [
            'alerts' => $alerts,
            'filter' => $filter,
        ]);
    }

    public function read(Alert $alert): RedirectResponse
    {
        $alert->markAsRead();

        return back();
    }

    public function readAll(): RedirectResponse
    {
        $count = Alert::unread()->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $count === 1 ? '1 alert marked as read.' : "{$count} alerts marked as read."]);

        return back();
    }
}
