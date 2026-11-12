<?php

namespace App\Providers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\PesaPalGateway;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, PesaPalGateway::class);
    }

    public function boot(): void
    {
        //
    }
}
