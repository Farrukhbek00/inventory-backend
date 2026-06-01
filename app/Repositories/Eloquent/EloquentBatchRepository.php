<?php

namespace App\Repositories\Eloquent;

use App\Models\Batch;
use App\Repositories\Contracts\BatchRepositoryInterface;

class EloquentBatchRepository implements BatchRepositoryInterface
{
    public function create(array $attributes): Batch
    {
        return Batch::query()->create($attributes);
    }

    public function findOrFail(int $batchId): Batch
    {
        return Batch::query()->findOrFail($batchId);
    }
}
