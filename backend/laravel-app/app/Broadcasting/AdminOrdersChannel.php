<?php

namespace App\Broadcasting;

use App\Models\User;


class AdminOrdersChannel
{
    public function join(User $user): bool
    {
        return $user->role === 'admin';
    }
}
