<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use App\Repositories\Contracts\LedgerRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleRefundService
{
    public function __construct(
        private readonly ClientOrderRepositoryInterface $clientOrderRepository,
        private readonly LedgerRepositoryInterface $ledgerRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $order = $this->clientOrderRepository->findOrFail((int) $payload['client_order_id']);
            $refundedAt = $payload['refunded_at'] ?? now()->toDateTimeString();
            $documentNo = $payload['number'] ?? $this->generateNumber();
            $now = now()->toDateTimeString();

            $productIds = collect($payload['products'])->pluck('id')->map('intval')->unique()->values()->all();
            $lotsByProduct = $this->ledgerRepository
                ->refundableSalesByOrder($order->id, $productIds)
                ->groupBy('product_id');

            $rows = [];
            foreach ($payload['products'] as $line) {
                $productId = (int) $line['id'];
                $remaining = (int) $line['qty'];
                $lots = $lotsByProduct->get($productId, collect());

                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $available = (int) $lot->remaining_qty;
                    if ($available <= 0) {
                        continue;
                    }

                    $picked = min($remaining, $available);

                    $rows[] = [
                        'product_id' => $productId,
                        'storage_id' => (int) $lot->storage_id,
                        'batch_id' => (int) $lot->batch_id,
                        'client_order_id' => $order->id,
                        'event_type' => InventoryMovementType::SaleRefund->value,
                        'document_no' => $documentNo,
                        'qty_signed' => $picked,
                        'unit_cost' => $lot->unit_cost,
                        'unit_price' => $lot->unit_price,
                        'occurred_at' => $refundedAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $remaining -= $picked;
                }

                if ($remaining > 0) {
                    throw ValidationException::withMessages([
                        'products' => ["Refund quantity is too high for product {$productId}."],
                    ]);
                }
            }

            $this->ledgerRepository->insertMany($rows);

            return [
                'sale_refund_number' => $documentNo,
                'order_id' => $order->id,
            ];
        });
    }

    private function generateNumber(): string
    {
        return 'SRF-' . now()->format('YmdHis') . '-' . random_int(100, 999);
    }
}
