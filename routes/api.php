<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('purchases')->group(function (): void {
    Route::post('/', [PurchaseController::class, 'store']);
    Route::post('/refunds', [PurchaseController::class, 'refund']);
});

Route::get('/products/available', [ReportController::class, 'availableProducts']);
Route::post('/orders', [OrderController::class, 'store']);
Route::post('/orders/refunds', [OrderController::class, 'refund']);
Route::get('/storages/remaining', [ReportController::class, 'remainingProducts']);
Route::get('/batches/profit', [ReportController::class, 'batchProfit']);
