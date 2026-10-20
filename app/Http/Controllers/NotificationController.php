<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $guardian = $this->resolveGuardian($request);

        $notifications = $guardian->notifications()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('notifications/Index', [
            'items' => $notifications->getCollection()->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => (string) ($notification->data['title'] ?? ''),
                'message' => (string) ($notification->data['message'] ?? ''),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->diffForHumans(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'prev_page_url' => $notifications->previousPageUrl(),
                'next_page_url' => $notifications->nextPageUrl(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $guardian = $this->resolveGuardian($request);

        $row = $guardian->notifications()->findOrFail($notification);
        $row->markAsRead();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notifikasi ditandai sudah dibaca.']);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $guardian = $this->resolveGuardian($request);

        $guardian->unreadNotifications()->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semua notifikasi ditandai sudah dibaca.']);

        return back();
    }

    private function resolveGuardian(Request $request): Guardian
    {
        return Guardian::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
