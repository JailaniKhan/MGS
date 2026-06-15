<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/payments-data', [PaymentController::class, 'customerIndex']);
Route::get('/payments/customers', [PaymentController::class, 'customerPaymentsPage']);
