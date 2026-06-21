<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/people', [App\Http\Controllers\PeopleController::class, 'index'])->name('people.index');
Route::get('/transactions', [App\Http\Controllers\TransactionsController::class, 'index'])->name('transactions.index');
Route::get('/inventory', [App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
Route::get('/ledger', [App\Http\Controllers\LedgerController::class, 'index'])->name('ledger.index');
Route::get('/ledger/{type}/{id}', [App\Http\Controllers\LedgerController::class, 'show'])->name('ledger.show');
Route::post('/ledger/{type}/{id}/payment', [App\Http\Controllers\LedgerController::class, 'paymentStore'])->name('ledger.payment.store');

Route::resource('customers', CustomerController::class);
Route::resource('categories', CategoryController::class);
Route::resource('products', ProductController::class);
Route::resource('units', UnitController::class);
Route::resource('suppliers', SupplierController::class);
Route::resource('purchases', PurchaseController::class)->except(['edit', 'update']);
Route::post('/purchases/{purchase}/status/{status}', [PurchaseController::class, 'status'])->name('purchases.status');
Route::post('/purchases/payment', [PurchaseController::class, 'paymentStore'])->name('purchases.payment.store');
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
