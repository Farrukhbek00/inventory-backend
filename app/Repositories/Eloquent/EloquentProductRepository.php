<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findManyByIds(array $ids): Collection
    {
        return Product::query()
            ->whereIn('id', $ids)
            ->get();
    }

    public function findOrFail(int $productId): Product
    {
        return Product::query()->findOrFail($productId);
    }
}
