<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseRefund;
use App\Repositories\Contracts\PurchaseRefundRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentPurchaseRefundRepository implements PurchaseRefundRepositoryInterface
{
    public function create(array $attributes): PurchaseRefund
    {
        return PurchaseRefund::query()->create($attributes);
    }

    public function createItems(PurchaseRefund $refund, array $items): Collection
    {
        $created = collect();

        foreach ($items as $item) {
            $created->push($refund->items()->create($item));
        }

        return $created;
    }
}
