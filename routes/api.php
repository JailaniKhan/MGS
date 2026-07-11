<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\V1\OtpAuthController;

Route::get('/payments-data', [PaymentController::class, 'customerIndex']);
Route::get('/payments/customers', [PaymentController::class, 'customerPaymentsPage']);

// OTP Auth
Route::post('/auth/send-otp', [OtpAuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [OtpAuthController::class, 'verifyOtp']);
