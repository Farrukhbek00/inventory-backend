<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly BatchRepositoryInterface $batchRepository,
        private readonly ClientOrderRepositoryInterface $clientOrderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly InventoryMovementRepositoryInterface $inventoryMovementRepository,
    ) {
    }

    public function createOrder(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $orderedAt = $payload['ordered_at'] ?? now()->toDateTimeString();

            $order = $this->clientOrderRepository->create([
                'client_id' => $payload['client_id'],
                'number' => $payload['number'] ?? $this->generateNumber(),
                'ordered_at' => $orderedAt,
            ]);

            $productIds = collect($payload['products'])->pluck('id')->unique()->values()->all();
            $products = $this->productRepository->findManyByIds($productIds)->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'products' => ['One or more products are invalid.'],
                ]);
            }

            $orderItemsPayload = [];
            $movementRows = [];

            foreach ($payload['products'] as $row) {
                $remaining = (int) $row['qty'];
                $product = $products->get($row['id']);
                $salePrice = $row['unit_price'] ?? $product->default_sale_price;

                $lots = $this->batchRepository->availableLotsByProduct((int) $row['id'], (int) $payload['storage_id']);

                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $available = (int) $lot->available_qty;
                    if ($available <= 0) {
                        continue;
                    }

                    $pickedQty = min($remaining, $available);

                    $orderItemsPayload[] = [
                        'product_id' => $row['id'],
                        'batch_id' => $lot->batch_id,
                        'qty' => $pickedQty,
                        'unit_price' => $salePrice,
                        'unit_cost' => $lot->unit_cost,
                    ];

                    $movementRows[] = [
                        'storage_id' => $payload['storage_id'],
                        'product_id' => $row['id'],
                        'batch_id' => $lot->batch_id,
                        'movement_type' => InventoryMovementType::Sale->value,
                        'reference_type' => $order->getMorphClass(),
                        'reference_id' => $order->id,
                        'qty' => -1 * $pickedQty,
                        'occurred_at' => $orderedAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $remaining -= $pickedQty;
                }

                if ($remaining > 0) {
                    throw ValidationException::withMessages([
                        'products' => ["Insufficient stock for product {$row['id']}."],
                    ]);
                }
            }

            $this->clientOrderRepository->createItems($order, $orderItemsPayload);
            $this->inventoryMovementRepository->createMany($movementRows);

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
