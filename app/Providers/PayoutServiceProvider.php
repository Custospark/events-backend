<?php

namespace App\Providers;

use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Eloquent\PayoutRepository;
use App\Services\Contracts\PayoutServiceInterface;
use App\Services\PayoutService;
use Illuminate\Support\ServiceProvider;

class PayoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PayoutRepositoryInterface::class, PayoutRepository::class);
        $this->app->bind(PayoutServiceInterface::class, PayoutService::class);
    }

    public function boot(): void
    {
        //
    }
}
