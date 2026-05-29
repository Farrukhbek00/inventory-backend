<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function findManyByIds(array $ids): Collection;

    public function findOrFail(int $productId): Product;
}
