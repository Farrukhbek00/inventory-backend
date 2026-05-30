<?php

namespace App\Services;

use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InventoryReportService
{
    public function __construct(
        private readonly InventoryMovementRepositoryInterface $inventoryMovementRepository,
    ) {
    }

    public function availableProducts(?int $storageId = null): Collection
    {
        return $this->inventoryMovementRepository->availableProducts($storageId);
    }

    public function remainingProductsByDate(string $date, ?int $storageId = null): Collection
    {
        $dateTime = Carbon::parse($date)->endOfDay();

        return $this->inventoryMovementRepository->remainingProductsByDate($dateTime, $storageId);
    }

    public function batchProfitSummary(): Collection
    {
        return $this->inventoryMovementRepository->batchProfitSummary();
    }
}
