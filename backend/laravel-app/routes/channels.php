<?php

use App\Broadcasting\AdminOrdersChannel;
use Illuminate\Support\Facades\Broadcast;


Broadcast::channel('admin.orders', AdminOrdersChannel::class, ['guards' => ['api']]);

