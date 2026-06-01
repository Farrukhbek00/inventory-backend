<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use App\Repositories\Contracts\LedgerRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly ClientOrderRepositoryInterface $clientOrderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LedgerRepositoryInterface $ledgerRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $productIds = collect($payload['products'])->pluck('id')->map('intval')->unique()->values()->all();
            $products = $this->productRepository->findManyByIds($productIds)->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'products' => ['One or more products are invalid.'],
                ]);
            }

            $orderedAt = $payload['ordered_at'] ?? now()->toDateTimeString();
            $storageId = isset($payload['storage_id']) ? (int) $payload['storage_id'] : null;

            $order = $this->clientOrderRepository->create([
                'client_id' => $payload['client_id'],
                'number' => $payload['number'] ?? $this->generateNumber(),
                'ordered_at' => $orderedAt,
            ]);

            $lotsByProduct = $this->ledgerRepository
                ->availableLotsForProducts($productIds, $storageId)
                ->groupBy('product_id');

            $now = now()->toDateTimeString();
            $rows = [];

            foreach ($payload['products'] as $line) {
                $productId = (int) $line['id'];
                $remaining = (int) $line['qty'];
                $salePrice = $line['unit_price'] ?? $products[$productId]->default_sale_price;
                $lots = $lotsByProduct->get($productId, collect());

                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $available = (int) $lot->available_qty;
                    if ($available <= 0) {
                        continue;
                    }

                    $picked = min($remaining, $available);

                    $rows[] = [
                        'product_id' => $productId,
                        'storage_id' => (int) $lot->storage_id,
                        'batch_id' => (int) $lot->batch_id,
                        'client_order_id' => $order->id,
                        'event_type' => InventoryMovementType::Sale->value,
                        'document_no' => $order->number,
                        'qty_signed' => -$picked,
                        'unit_cost' => $lot->unit_cost,
                        'unit_price' => $salePrice,
                        'occurred_at' => $orderedAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $remaining -= $picked;
                }

                if ($remaining > 0) {
                    throw ValidationException::withMessages([
                        'products' => ["Insufficient stock for product {$productId}."],
                    ]);
                }
            }

            $this->ledgerRepository->insertMany($rows);

            return [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ];
        });
    }

    private function generateNumber(): string
    {
        return 'ORD-' . now()->format('YmdHis') . '-' . random_int(100, 999);
    }
}
