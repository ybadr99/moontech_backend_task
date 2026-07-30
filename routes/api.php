<?php

use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PhoneVerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
    ->middleware('throttle:otp');

Route::post('/reset-password', [PasswordResetController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/phone/verify', [PhoneVerificationController::class, 'verify']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
});

Route::post('/phone/resend', [PhoneVerificationController::class, 'resend'])
    ->middleware('throttle:otp');

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('products', AdminProductController::class);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
});
