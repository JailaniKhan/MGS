<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GoogleController;
use App\Http\Controllers\Api\V1\OpenWaWebhookController;
use App\Http\Controllers\Api\V1\OtpAuthController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

// Protected API routes (require Sanctum authentication)
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Sync endpoints
    Route::post('/sync/push', [SyncController::class, 'push']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);

    // Accounts
    Route::apiResource('accounts', AccountController::class);
    Route::get('/accounts/{account}/balance', [AccountController::class, 'balance']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);

    // Dashboard summary
    Route::get('/dashboard', [DashboardController::class, '__invoke']);
});

// OTP Auth (intentionally public — no auth required to request/verify OTP)
Route::post('/auth/send-otp', [OtpAuthController::class, 'sendOtp'])->middleware('throttle:3,1');
Route::post('/auth/verify-otp', [OtpAuthController::class, 'verifyOtp'])->middleware('throttle:5,1');

// Google social login (intentionally public — OAuth flows require unauthenticated access)
Route::get('/auth/google/redirect', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/auth/google/exchange', [GoogleController::class, 'exchange']);

// OpenWA inbound webhook (self-hosted WhatsApp gateway — validated via webhook secret)
Route::post('/webhooks/openwa', [OpenWaWebhookController::class, 'handle']);
