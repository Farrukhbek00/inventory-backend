<?php

namespace App\Providers;

use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseRefundRepositoryInterface;
use App\Repositories\Eloquent\EloquentBatchRepository;
use App\Repositories\Eloquent\EloquentClientOrderRepository;
use App\Repositories\Eloquent\EloquentInventoryMovementRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentPurchaseRefundRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BatchRepositoryInterface::class, EloquentBatchRepository::class);
        $this->app->bind(ClientOrderRepositoryInterface::class, EloquentClientOrderRepository::class);
        $this->app->bind(InventoryMovementRepositoryInterface::class, EloquentInventoryMovementRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(PurchaseRefundRepositoryInterface::class, EloquentPurchaseRefundRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
