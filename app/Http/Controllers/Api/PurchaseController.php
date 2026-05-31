<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseToStorageRequest;
use App\Http\Requests\RefundPurchasedProductsRequest;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchaseService,
    ) {
    }

    public function store(PurchaseToStorageRequest $request): JsonResponse
    {
        $result = $this->purchaseService->purchaseToStorage($request->validated());

        return response()->json($result, 201);
    }

    public function refund(RefundPurchasedProductsRequest $request): JsonResponse
    {
        $result = $this->purchaseService->refundPurchasedProducts($request->validated());

        return response()->json($result, 201);
    }
}
