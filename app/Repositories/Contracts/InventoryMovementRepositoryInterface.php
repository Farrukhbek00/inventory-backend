<?php

namespace App\Repositories\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface InventoryMovementRepositoryInterface
{
    public function createMany(array $rows): void;

    public function availableProducts(?int $storageId = null): Collection;

    public function availableQtyByProduct(int $productId, ?int $storageId = null): int;

    public function remainingProductsByDate(CarbonInterface $date, ?int $storageId = null): Collection;

    public function batchProfitSummary(): Collection;
}
