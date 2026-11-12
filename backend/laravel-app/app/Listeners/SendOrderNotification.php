<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Models\Notification;
use App\Models\User;

class SendOrderNotification
{
    public function handle($event)
    {

        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type'    => 'order_created',
                'message' => 'تم إنشاء طلب جديد برقم #' . $event->order->id,
            ]);
        }
    }
}