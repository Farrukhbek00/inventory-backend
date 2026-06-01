<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\LedgerRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly BatchRepositoryInterface $batchRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LedgerRepositoryInterface $ledgerRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function purchaseToStorage(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $productIds = collect($payload['products'])->pluck('id')->map('intval')->unique()->values()->all();
            $products = $this->productRepository->findManyByIds($productIds);

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

            $now = now()->toDateTimeString();
            $rows = collect($payload['products'])
                ->map(fn (array $row): array => [
                    'product_id' => (int) $row['id'],
                    'storage_id' => $batch->storage_id,
                    'batch_id' => $batch->id,
                    'client_order_id' => null,
                    'event_type' => InventoryMovementType::Purchase->value,
                    'document_no' => $batch->number,
                    'qty_signed' => (int) $row['qty'],
                    'unit_cost' => $row['unit_cost'],
                    'unit_price' => null,
                    'occurred_at' => $purchasedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            $this->ledgerRepository->insertMany($rows);

            return [
                'batch_id' => $batch->id,
                'batch_number' => $batch->number,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refundPurchasedProducts(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $batch = $this->batchRepository->findOrFail((int) $payload['batch_id']);
            $refundedAt = $payload['refunded_at'] ?? now()->toDateTimeString();
            $documentNo = $payload['number'] ?? $this->generateNumber('PRF');
            $now = now()->toDateTimeString();

            $rows = [];
            foreach ($payload['products'] as $row) {
                $productId = (int) $row['id'];
                $qty = (int) $row['qty'];

                $available = $this->ledgerRepository->availableQtyInBatch($batch->id, $productId);
                if ($qty > $available) {
                    throw ValidationException::withMessages([
                        'products' => ["Insufficient unsold quantity for product {$productId}."],
                    ]);
                }

                $rows[] = [
                    'product_id' => $productId,
                    'storage_id' => $batch->storage_id,
                    'batch_id' => $batch->id,
                    'client_order_id' => null,
                    'event_type' => InventoryMovementType::PurchaseRefund->value,
                    'document_no' => $documentNo,
                    'qty_signed' => -$qty,
                    'unit_cost' => $row['unit_cost'] ?? null,
                    'unit_price' => null,
                    'occurred_at' => $refundedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Fill in unit_cost from purchase when omitted.
            $missingCostProductIds = collect($rows)
                ->filter(fn (array $r): bool => $r['unit_cost'] === null)
                ->pluck('product_id')
                ->unique()
                ->values()
                ->all();

            if ($missingCostProductIds !== []) {
                $costMap = $this->ledgerRepository
                    ->availableLotsForProducts($missingCostProductIds, $batch->storage_id)
                    ->where('batch_id', $batch->id)
                    ->pluck('unit_cost', 'product_id');

                foreach ($rows as &$r) {
                    if ($r['unit_cost'] === null) {
                        $r['unit_cost'] = $costMap[$r['product_id']] ?? null;
                    }
                }
                unset($r);
            }

            $this->ledgerRepository->insertMany($rows);

            return [
                'purchase_refund_number' => $documentNo,
                'batch_id' => $batch->id,
            ];
        });
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix . '-' . now()->format('YmdHis') . '-' . random_int(100, 999);
    }
}
