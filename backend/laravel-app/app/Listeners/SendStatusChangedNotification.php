<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Models\Notification;

class SendStatusChangedNotification
{

    public function handle($event)
    {

        $order = $event->order;

        Notification::create([
            'user_id' => $order->user_id,
            'type'    => 'order_status_changed',
            'message' => 'تم تحديث حالة طلبك رقم #' . $order->id . ' لتصبح: ' . $order->status,
        ]);
    }
}