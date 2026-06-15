<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UnitController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('customers', CustomerController::class);
Route::resource('categories', CategoryController::class);
Route::resource('products', ProductController::class);
Route::resource('units', UnitController::class);
Route::resource('orders', OrderController::class)->except(['show']);

Route::get('/orders/{order}/show', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{order}/status/{status}', [OrderController::class, 'status'])->name('orders.status');
Route::get('/orders/product-price/{product}', [OrderController::class, 'getProductPrice'])->name('orders.product-price');

// Payments
Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
Route::get('/payments/customers', [PaymentController::class, 'customerPaymentsPage'])->name('payments.customers');
Route::get('/payments/order/{order}', [PaymentController::class, 'show'])->name('payments.show');
Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
