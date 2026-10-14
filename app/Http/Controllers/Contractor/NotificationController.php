<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /** Latest notifications; opening the list marks them read (the page still shows which were new). */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $notifications = $user->notifications()->limit(50)->get()->map(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            ...$n->data,
            'title' => __((string) ($n->data['title'] ?? ''), (array) ($n->data['replace'] ?? [])),
            'body' => __((string) ($n->data['body'] ?? ''), (array) ($n->data['replace'] ?? [])),
            'unread' => $n->read_at === null,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);
        $user->unreadNotifications()->update(['read_at' => now()]);

        return Inertia::render('notifications', ['notifications' => $notifications]);
    }
}
