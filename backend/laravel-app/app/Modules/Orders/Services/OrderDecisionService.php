<?php

namespace App\Modules\Orders\Services;

use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Contracts\StockProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Modules\Orders\Models\OrderStatusHistory;

class OrderDecisionService
{
    public const DECISION_APPROVED = 'approved';

    public const DECISION_REJECTED = 'rejected';

    public const DECISION_TO_STATUS = [
        self::DECISION_APPROVED => 'CONFIRMED',
        self::DECISION_REJECTED => 'CANCELLED',
    ];

    /**
     * للأمام فقط، بدون قفز. DELIVERED وCANCELLED نهائيان.
     */
    public const TRANSITIONS = [
        'PENDING' => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['DELIVERED'],
    ];

    public function __construct(protected StockProvider $inventory)
    {
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * المصدر الوحيد لتغيير حالة الطلب (العميل والأدمن).
     *
     * @throws InvalidOrderTransitionException إذا الانتقال غير مسموح
     * @throws RuntimeException إذا تعذّر إرجاع المخزون (الحالة تبقى كما هي)
     */
    public function changeStatus(
        int $orderId,
        string $newStatus,
        User $actor,
        ?string $note = null,
        ?string $notificationType = null,
        ?string $notificationMessage = null
    ): Order {
        [$order, $oldStatus] = DB::transaction(function () use ($orderId, $newStatus, $actor, $note, $notificationType, $notificationMessage) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $oldStatus = $order->status;

            if (! self::canTransition($oldStatus, $newStatus)) {
                throw new InvalidOrderTransitionException($oldStatus, $newStatus);
            }

            // إرجاع المخزون مرة واحدة فقط: الانتقال إلى CANCELLED ممكن مرة وحدة بسبب القفل وجدول الانتقالات
            if ($newStatus === 'CANCELLED' && $order->reservation_id) {
                if (! $this->inventory->release($order->reservation_id)) {
                    throw new RuntimeException('Inventory release returned false for reservation '.$order->reservation_id);
                }
            }

            $order->update(['status' => $newStatus]);

OrderStatusHistory::create([
    'order_id' => $order->id,
    'old_status' => $oldStatus,
    'new_status' => $newStatus,
    'changed_by' => $actor->id,
    'note' => $note,
]);

            DB::table('notifications')->insert([
                'user_id' => $order->user_id,
                'type' => $notificationType ?? 'OrderStatusUpdated',
                'message' => ($notificationMessage ?? $this->defaultMessage($order->id, $newStatus))
                    .($note ? ' — ملاحظة المسؤول: '.$note : ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [$order, $oldStatus];
        });

        event(new OrderStatusChanged($order, $oldStatus, $newStatus, $actor->id, $note));

        return $order;
    }

    /**
     * توافق مع قرار approved/rejected القديم.
     */
    public function decide(int $orderId, string $decision, User $admin, ?string $note = null): Order
    {
        return $this->changeStatus($orderId, self::DECISION_TO_STATUS[$decision], $admin, $note);
    }

    private function defaultMessage(int $id, string $status): string
    {
        return match ($status) {
            'CONFIRMED' => 'تمت الموافقة على طلبك رقم #'.$id,
            'SHIPPED' => 'تم شحن طلبك رقم #'.$id,
            'DELIVERED' => 'تم تسليم طلبك رقم #'.$id,
            'CANCELLED' => 'تم إلغاء طلبك رقم #'.$id.' وإرجاع المخزون',
            default => 'تم تغيير حالة طلبك رقم #'.$id.' إلى: '.$status,
        };
    }
}