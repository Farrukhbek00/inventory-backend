<?php

namespace App\Repositories\Eloquent;

use App\Models\SaleRefund;
use App\Repositories\Contracts\SaleRefundRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentSaleRefundRepository implements SaleRefundRepositoryInterface
{
    public function create(array $attributes): SaleRefund
    {
        return SaleRefund::query()->create($attributes);
    }

    public function createItems(SaleRefund $refund, array $items): Collection
    {
        $created = collect();

        foreach ($items as $item) {
            $created->push($refund->items()->create($item));
        }

        return $created;
    }
}
