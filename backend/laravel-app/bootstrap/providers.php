<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ModularRoutingServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
    App\Modules\Orders\Providers\OrderServiceProvider::class,
    L5Swagger\L5SwaggerServiceProvider::class,
];
