<?php

namespace App\Modules\Orders\Policies;

use App\Models\User;
use App\Modules\Orders\Models\Order;

class OrderPolicy
{

    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->role === 'admin';
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->role === 'admin';
    }
}