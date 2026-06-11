<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request) {
        return Notification::where('recipient_id', $request->user()->id)
            ->latest()
            ->paginate(20);
    }

    public function unreadCount(Request $request){
        return Notification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(string $id, Request $request){
        Notification::where('id', $id)
            ->where('recipient_id', $request->user()->id)
            ->update([
                'read_at' => now()
            ]);
    }

    public function destroy(string $id, Request $request){
        Notification::where('id', $id)
            ->where('recipient_id', $request->user()->id)
            ->delete();
    }

}
