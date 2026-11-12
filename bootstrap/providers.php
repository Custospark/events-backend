<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\OrderItemServiceProvider;
use App\Providers\OrderServiceProvider;
use App\Providers\PaymentGatewayServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\PayoutServiceProvider;
use App\Providers\RoleServiceProvider;
use App\Providers\TenantServiceProvider;
use App\Providers\TicketServiceProvider;
use App\Providers\TicketTypeServiceProvider;
use App\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    TenantServiceProvider::class,
    RoleServiceProvider::class,
    UserServiceProvider::class,
    EventServiceProvider::class,
    TicketTypeServiceProvider::class,
    OrderServiceProvider::class,
    OrderItemServiceProvider::class,
    TicketServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    PaymentServiceProvider::class,
    PayoutServiceProvider::class,
    DashboardServiceProvider::class,
];
