<?php

namespace App\Providers;

use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\Eloquent\TicketTypeRepository;
use App\Services\Contracts\TicketTypeServiceInterface;
use App\Services\TicketTypeService;
use Illuminate\Support\ServiceProvider;

class TicketTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketTypeRepositoryInterface::class, TicketTypeRepository::class);
        $this->app->bind(TicketTypeServiceInterface::class, TicketTypeService::class);
    }

    public function boot(): void
    {
        //
    }
}
