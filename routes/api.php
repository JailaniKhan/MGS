<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\V1\OtpAuthController;
use App\Http\Controllers\Api\V1\OpenWaWebhookController;
use App\Http\Controllers\Api\V1\GoogleController;

Route::get('/payments-data', [PaymentController::class, 'customerIndex']);
Route::get('/payments/customers', [PaymentController::class, 'customerPaymentsPage']);

// OTP Auth
Route::post('/auth/send-otp', [OtpAuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [OtpAuthController::class, 'verifyOtp']);

// Google social login
Route::get('/auth/google/redirect', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/auth/google/exchange', [GoogleController::class, 'exchange']);

// OpenWA inbound webhook (self-hosted WhatsApp gateway)
Route::post('/webhooks/openwa', [OpenWaWebhookController::class, 'handle']);
