<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateClientOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function store(CreateClientOrderRequest $request): JsonResponse
    {
        $result = $this->orderService->createOrder($request->validated());

        return response()->json($result, 201);
    }
}
