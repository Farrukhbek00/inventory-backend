<?php

namespace App\Repositories\Eloquent;

use App\Models\ClientOrder;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;

class EloquentClientOrderRepository implements ClientOrderRepositoryInterface
{
    public function create(array $attributes): ClientOrder
    {
        return ClientOrder::query()->create($attributes);
    }

    public function findOrFail(int $orderId): ClientOrder
    {
        return ClientOrder::query()->findOrFail($orderId);
    }
}
