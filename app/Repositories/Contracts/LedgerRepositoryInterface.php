<?php

namespace App\Repositories\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface LedgerRepositoryInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): void;

    /**
     * @param  array<int, int>  $productIds
     */
    public function availableLotsForProducts(array $productIds, ?int $storageId = null): Collection;

    public function availableProducts(?int $storageId = null): Collection;

    public function remainingProductsByDate(CarbonInterface $date, ?int $storageId = null): Collection;

    public function batchProfitSummary(): Collection;

    public function availableQtyInBatch(int $batchId, int $productId): int;

    /**
     * @param  array<int, int>  $productIds
     */
    public function refundableSalesByOrder(int $orderId, array $productIds): Collection;
}
