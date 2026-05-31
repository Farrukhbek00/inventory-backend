<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateClientOrderRequest;
use App\Http\Requests\RefundClientOrderRequest;
use App\Services\OrderService;
use App\Services\SaleRefundService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly SaleRefundService $saleRefundService,
    ) {
    }

    public function store(CreateClientOrderRequest $request): JsonResponse
    {
        $result = $this->orderService->createOrder($request->validated());

        return response()->json($result, 201);
    }

    public function refund(RefundClientOrderRequest $request): JsonResponse
    {
        $result = $this->saleRefundService->refund($request->validated());

        return response()->json($result, 201);
    }
}
