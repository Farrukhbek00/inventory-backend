<?php

namespace App\Repositories\Contracts;

use App\Models\ClientOrder;

interface ClientOrderRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ClientOrder;

    public function findOrFail(int $orderId): ClientOrder;
}
