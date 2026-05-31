<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use App\Repositories\Contracts\SaleRefundRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleRefundService
{
    public function __construct(
        private readonly ClientOrderRepositoryInterface $clientOrderRepository,
        private readonly SaleRefundRepositoryInterface $saleRefundRepository,
        private readonly InventoryMovementRepositoryInterface $inventoryMovementRepository,
    ) {
    }

    public function refund(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $order = $this->clientOrderRepository->findOrFail($payload['client_order_id']);
            $refundedAt = $payload['refunded_at'] ?? now()->toDateTimeString();

            $refundItems = [];
            $movementRows = [];

            foreach ($payload['products'] as $row) {
                $remaining = (int) $row['qty'];
                $lots = $this->clientOrderRepository->refundableItemsByOrderAndProduct($order->id, (int) $row['id']);

                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $available = (int) $lot->remaining_qty;
                    if ($available <= 0) {
                        continue;
                    }

                    $pickedQty = min($remaining, $available);

                    $refundItems[] = [
                        'client_order_item_id' => $lot->client_order_item_id,
                        'qty' => $pickedQty,
                        'unit_price' => $lot->unit_price,
                        'unit_cost' => $lot->unit_cost,
                    ];

                    $movementRows[] = [
                        'storage_id' => $lot->storage_id,
                        'product_id' => $row['id'],
                        'batch_id' => $lot->batch_id,
                        'movement_type' => InventoryMovementType::SaleRefund->value,
                        'reference_type' => $order->getMorphClass(),
                        'reference_id' => $order->id,
                        'qty' => $pickedQty,
                        'occurred_at' => $refundedAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $remaining -= $pickedQty;
                }

                if ($remaining > 0) {
                    throw ValidationException::withMessages([
                        'products' => ["Refund quantity is too high for product {$row['id']}."],
                    ]);
                }
            }

            $refund = $this->saleRefundRepository->create([
                'client_order_id' => $order->id,
                'number' => $payload['number'] ?? $this->generateNumber(),
                'refunded_at' => $refundedAt,
                'reason' => $payload['reason'] ?? null,
            ]);

            $this->saleRefundRepository->createItems($refund, $refundItems);

            $movementRows = collect($movementRows)
                ->map(fn (array $row): array => $row + [
                    'reference_type' => $refund->getMorphClass(),
                    'reference_id' => $refund->id,
                ])
                ->all();

            $this->inventoryMovementRepository->createMany($movementRows);

            return [
                'sale_refund_id' => $refund->id,
                'sale_refund_number' => $refund->number,
            ];
        });
    }

    private function generateNumber(): string
    {
        return 'SRF-' . now()->format('YmdHis') . '-' . random_int(100, 999);
    }
}
