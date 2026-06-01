<?php

namespace App\Repositories\Contracts;

use App\Models\Batch;

interface BatchRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Batch;

    public function findOrFail(int $batchId): Batch;
}
