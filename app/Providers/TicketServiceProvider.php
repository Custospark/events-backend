<?php

namespace App\Providers;

use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Eloquent\TicketRepository;
use App\Services\Contracts\TicketServiceInterface;
use App\Services\TicketService;
use Illuminate\Support\ServiceProvider;

class TicketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(TicketServiceInterface::class, TicketService::class);
    }

    public function boot(): void
    {
        //
    }
}
