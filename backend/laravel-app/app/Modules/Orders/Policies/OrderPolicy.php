<?php

namespace App\Modules\Orders\Policies;

use App\Models\User;
use App\Modules\Orders\Models\Order;

class OrderPolicy
{
    /**
     * تحديد ما إذا كان المستخدم يملك صلاحية عرض الطلب.
     * الشروط: مالك الطلب نفسه أو أن يكون المستخدم Admin.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->role === 'admin';
    }

    /**
     * تحديد ما إذا كان المستخدم يملك صلاحية تغيير حالة الطلب.
     * الشروط: الـ Admin حصراً.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->role === 'admin';
    }
}