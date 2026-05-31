<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\BatchItem;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseRefundRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly BatchRepositoryInterface $batchRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PurchaseRefundRepositoryInterface $purchaseRefundRepository,
        private readonly InventoryMovementRepositoryInterface $inventoryMovementRepository,
    ) {
    }

    public function purchaseToStorage(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $productIds = collect($payload['products'])->pluck('id')->unique()->values()->all();
            $products = $this->productRepository->findManyByIds($productIds)->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'products' => ['One or more products are invalid.'],
                ]);
            }

            $purchasedAt = $payload['purchased_at'] ?? now()->toDateTimeString();

            $batch = $this->batchRepository->create([
                'provider_id' => $payload['provider_id'],
                'storage_id' => $payload['storage_id'],
                'number' => $payload['number'] ?? $this->generateNumber('BAT'),
                'purchased_at' => $purchasedAt,
            ]);

            $itemsPayload = collect($payload['products'])
                ->map(static fn (array $product): array => [
                    'product_id' => $product['id'],
                    'qty' => $product['qty'],
                    'unit_cost' => $product['unit_cost'],
                ])
                ->all();

            $items = $this->batchRepository->createItems($batch, $itemsPayload);

            $movementRows = $items->map(fn ($item): array => [
                'storage_id' => $batch->storage_id,
                'product_id' => $item->product_id,
                'batch_id' => $batch->id,
                'movement_type' => InventoryMovementType::Purchase->value,
                'reference_type' => $batch->getMorphClass(),
                'reference_id' => $batch->id,
                'qty' => $item->qty,
                'occurred_at' => $purchasedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            $this->inventoryMovementRepository->createMany($movementRows);

            return [
                'batch_id' => $batch->id,
                'batch_number' => $batch->number,
            ];
        });
    }

    public function refundPurchasedProducts(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $batch = $this->batchRepository->findOrFail($payload['batch_id']);
            $batchItems = $batch->items->keyBy('product_id');
            $refundedAt = $payload['refunded_at'] ?? now()->toDateTimeString();

            $refundItems = [];

            foreach ($payload['products'] as $row) {
                /** @var BatchItem|null $batchItem */
                $batchItem = $batchItems->get((int) $row['id']);

                if ($batchItem === null) {
                    throw ValidationException::withMessages([
                        'products' => ["Product {$row['id']} is not in this batch."],
                    ]);
                }

                $availableQty = $this->batchRepository->availableQtyByBatchAndProduct($batch->id, $row['id']);
                if ($row['qty'] > $availableQty) {
                    throw ValidationException::withMessages([
                        'products' => ["Insufficient unsold quantity for product {$row['id']}."],
                    ]);
                }

                $refundItems[] = [
                    'batch_item_id' => $batchItem->id,
                    'qty' => $row['qty'],
                    'unit_cost' => $batchItem->unit_cost,
                ];
            }

            $refund = $this->purchaseRefundRepository->create([
                'batch_id' => $batch->id,
                'number' => $payload['number'] ?? $this->generateNumber('PRF'),
                'refunded_at' => $refundedAt,
                'reason' => $payload['reason'] ?? null,
            ]);

            $this->purchaseRefundRepository->createItems($refund, $refundItems);

            $movementRows = collect($payload['products'])
                ->map(function (array $row) use ($batch, $refundedAt, $refund): array {
                    return [
                        'storage_id' => $batch->storage_id,
                        'product_id' => (int) $row['id'],
                        'batch_id' => $batch->id,
                        'movement_type' => InventoryMovementType::PurchaseRefund->value,
                        'reference_type' => $refund->getMorphClass(),
                        'reference_id' => $refund->id,
                        'qty' => -1 * (int) $row['qty'],
                        'occurred_at' => $refundedAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->all();

            $this->inventoryMovementRepository->createMany($movementRows);

            return [
                'purchase_refund_id' => $refund->id,
                'purchase_refund_number' => $refund->number,
            ];
        });
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix . '-' . now()->format('YmdHis') . '-' . random_int(100, 999);
    }
}
