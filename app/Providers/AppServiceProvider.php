<?php

namespace App\Providers;

use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use App\Repositories\Contracts\LedgerRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\EloquentBatchRepository;
use App\Repositories\Eloquent\EloquentClientOrderRepository;
use App\Repositories\Eloquent\EloquentLedgerRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BatchRepositoryInterface::class, EloquentBatchRepository::class);
        $this->app->bind(ClientOrderRepositoryInterface::class, EloquentClientOrderRepository::class);
        $this->app->bind(LedgerRepositoryInterface::class, EloquentLedgerRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
