<?php

use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('api.leave-requests.index');
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])->name('api.leave-balances.index');
});
