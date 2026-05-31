<?php

namespace App\Repositories\Contracts;

use App\Models\ClientOrder;
use Illuminate\Support\Collection;

interface ClientOrderRepositoryInterface
{
    public function create(array $attributes): ClientOrder;

    public function createItems(ClientOrder $order, array $items): Collection;

    public function findOrFail(int $orderId): ClientOrder;

    public function refundableItemsByOrderAndProduct(int $orderId, int $productId): Collection;
}
