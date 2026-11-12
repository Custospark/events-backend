<?php

namespace App\Providers;

use App\Repositories\Contracts\OrderItemRepositoryInterface;
use App\Repositories\Eloquent\OrderItemRepository;
use App\Services\Contracts\OrderItemServiceInterface;
use App\Services\OrderItemService;
use Illuminate\Support\ServiceProvider;

class OrderItemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderItemRepositoryInterface::class, OrderItemRepository::class);
        $this->app->bind(OrderItemServiceInterface::class, OrderItemService::class);
    }

    public function boot(): void
    {
        //
    }
}
