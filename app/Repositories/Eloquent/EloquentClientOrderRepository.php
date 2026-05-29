<?php

namespace App\Repositories\Eloquent;

use App\Models\ClientOrder;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentClientOrderRepository implements ClientOrderRepositoryInterface
{
    public function create(array $attributes): ClientOrder
    {
        return ClientOrder::query()->create($attributes);
    }

    public function createItems(ClientOrder $order, array $items): Collection
    {
        $created = collect();

        foreach ($items as $item) {
            $created->push($order->items()->create($item));
        }

        return $created;
    }

    public function findOrFail(int $orderId): ClientOrder
    {
        return ClientOrder::query()->with(['items'])->findOrFail($orderId);
    }
}
