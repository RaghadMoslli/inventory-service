<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($notifications);
    }

    
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم تحديث الإشعار بنجاح',
            'data' => $notification
        ]);
    }
}