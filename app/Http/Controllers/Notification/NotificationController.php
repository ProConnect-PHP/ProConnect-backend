<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification\Notification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return Notification::where('recipient_id', $request->user()->id)
            ->latest()
            ->paginate(20);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => Notification::where('recipient_id', $request->user()->id)
                ->whereNull('read_at')
                ->count()
        ]);
    }

    public function markAsRead(string $id, Request $request)
    {
        $updated = Notification::where('id', $id)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if (!$updated) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request)
    {
        Notification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy(string $id, Request $request)
    {
        $deleted = Notification::where('id', $id)
            ->where('recipient_id', $request->user()->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['message' => 'Notification deleted']);
    }
}