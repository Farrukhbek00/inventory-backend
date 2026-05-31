<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableProductsRequest;
use App\Http\Requests\RemainingProductsRequest;
use App\Services\InventoryReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly InventoryReportService $inventoryReportService,
    ) {
    }

    public function availableProducts(AvailableProductsRequest $request): JsonResponse
    {
        $result = $this->inventoryReportService->availableProducts($request->integer('storage_id'));

        return response()->json($result);
    }

    public function remainingProducts(RemainingProductsRequest $request): JsonResponse
    {
        $result = $this->inventoryReportService->remainingProductsByDate(
            $request->string('date')->value(),
            $request->integer('storage_id'),
        );

        return response()->json($result);
    }

    public function batchProfit(): JsonResponse
    {
        $result = $this->inventoryReportService->batchProfitSummary();

        return response()->json($result);
    }
}
