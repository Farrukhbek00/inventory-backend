<?php

namespace App\Repositories\Contracts;

use App\Models\Batch;
use Illuminate\Support\Collection;

interface BatchRepositoryInterface
{
    public function create(array $attributes): Batch;

    public function createItems(Batch $batch, array $items): Collection;

    public function findOrFail(int $batchId): Batch;

    public function availableLotsByProduct(int $productId, ?int $storageId = null): Collection;

    public function availableQtyByBatchAndProduct(int $batchId, int $productId): int;
}
