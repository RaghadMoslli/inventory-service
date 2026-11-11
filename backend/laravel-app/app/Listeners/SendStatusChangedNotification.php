<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Models\Notification;

class SendStatusChangedNotification
{
    /**
     * Handle the event.
     */
    public function handle($event)
    {
        // $event->order يحمل بيانات الطلب الذي تغيرت حالته
        $order = $event->order;

        // إنشاء إشعار موجه حصراً لصاحب الطلب (user_id)
        Notification::create([
            'user_id' => $order->user_id,
            'type'    => 'order_status_changed',
            'message' => 'تم تحديث حالة طلبك رقم #' . $order->id . ' لتصبح: ' . $order->status,
        ]);
    }
}