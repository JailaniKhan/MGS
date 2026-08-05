<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CashbookController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SalaryController;

// Auth routes (no auth middleware)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/login/phone', function () {
    return view('auth.otp-login');
})->name('login.phone');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/language', [App\Http\Controllers\SettingsController::class, 'updateLanguage'])->name('language.update');
    Route::get('/settings/openwa', [App\Http\Controllers\SettingsController::class, 'openwa'])->name('settings.openwa');
    Route::get('/settings/openwa/qr', [App\Http\Controllers\SettingsController::class, 'openwaQr'])->name('settings.openwa.qr');
    Route::post('/settings/openwa/pairing-code', [App\Http\Controllers\SettingsController::class, 'openwaPairingCode'])->name('settings.openwa.pairing');
    Route::get('/settings/openwa/pairing-status', [App\Http\Controllers\SettingsController::class, 'openwaPairingStatus'])->name('settings.openwa.pairing-status');
    Route::post('/settings/openwa/test-send', [App\Http\Controllers\SettingsController::class, 'openwaTestSend'])->name('settings.openwa.test');
    Route::post('/settings/openwa/restart', [App\Http\Controllers\SettingsController::class, 'openwaRestart'])->name('settings.openwa.restart');

    Route::get('/people', [App\Http\Controllers\PeopleController::class, 'index'])->name('people.index');
    Route::get('/transactions', [App\Http\Controllers\TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('/inventory', [App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/ledger', [App\Http\Controllers\LedgerController::class, 'index'])->name('ledger.index');
    Route::get('/ledger/{type}/{id}', [App\Http\Controllers\LedgerController::class, 'show'])->name('ledger.show');
    Route::get('/ledger/{type}/{id}/pdf', [App\Http\Controllers\LedgerController::class, 'downloadPdf'])->name('ledger.pdf');
    Route::post('/ledger/{type}/{id}/payment', [App\Http\Controllers\LedgerController::class, 'paymentStore'])->name('ledger.payment.store');

    Route::resource('customers', CustomerController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('expenses', ExpenseController::class);
    Route::resource('products', ProductController::class);
    Route::resource('units', UnitController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('purchases', PurchaseController::class)->except(['edit', 'update']);
    Route::post('/purchases/{purchase}/status/{status}', [PurchaseController::class, 'status'])->name('purchases.status');
    Route::post('/purchases/payment', [PurchaseController::class, 'paymentStore'])->name('purchases.payment.store');
    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
    Route::post('/purchases/{purchase}/send-whatsapp', [PurchaseController::class, 'sendWhatsApp'])->name('purchases.send-whatsapp');
    Route::resource('orders', OrderController::class)->except(['show']);

    Route::get('/orders/{order}/show', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/print', [OrderController::class, 'print'])->name('orders.print');
    Route::post('/orders/{order}/send-whatsapp', [OrderController::class, 'sendWhatsApp'])->name('orders.send-whatsapp');
    Route::post('/orders/{order}/status/{status}', [OrderController::class, 'status'])->name('orders.status');
    Route::get('/orders/product-price/{product}', [OrderController::class, 'getProductPrice'])->name('orders.product-price');

    Route::prefix('orders/returns')->name('orders.returns.')->group(function () {
        Route::get('/', [OrderReturnController::class, 'index'])->name('index');
        Route::get('/create', [OrderReturnController::class, 'create'])->name('create');
        Route::post('/', [OrderReturnController::class, 'store'])->name('store');
        Route::get('/{orderReturn}', [OrderReturnController::class, 'show'])->name('show');
        Route::delete('/{orderReturn}', [OrderReturnController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('purchases/returns')->name('purchases.returns.')->group(function () {
        Route::get('/', [PurchaseReturnController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseReturnController::class, 'create'])->name('create');
        Route::post('/', [PurchaseReturnController::class, 'store'])->name('store');
        Route::get('/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('show');
        Route::delete('/{purchaseReturn}', [PurchaseReturnController::class, 'destroy'])->name('destroy');
    });

    // Reports
    Route::get('/reports/profit-loss', [App\Http\Controllers\ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('/reports/balance-sheet', [App\Http\Controllers\ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/stock', [App\Http\Controllers\ReportController::class, 'stockReport'])->name('reports.stock');
    Route::get('/reports/daybook', [App\Http\Controllers\ReportController::class, 'daybook'])->name('reports.daybook');
    Route::get('/reports/aging', [App\Http\Controllers\ReportController::class, 'aging'])->name('reports.aging');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/customers', [PaymentController::class, 'customerPaymentsPage'])->name('payments.customers');
    Route::get('/payments/order/{order}', [PaymentController::class, 'show'])->name('payments.show');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // Backup
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::get('/backup/create', [BackupController::class, 'create'])->name('backup.create');
    Route::post('/backup', [BackupController::class, 'store'])->name('backup.store');
    Route::get('/backup/download/{filename}', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('/backup/{filename}', [BackupController::class, 'destroy'])->name('backup.destroy');

    // Cashbook
    Route::get('/cashbook', [CashbookController::class, 'index'])->name('cashbook.index');
    Route::get('/cashbook/create', [CashbookController::class, 'create'])->name('cashbook.create');
    Route::post('/cashbook', [CashbookController::class, 'store'])->name('cashbook.store');
    Route::get('/cashbook/{type}/{id}', [CashbookController::class, 'person'])->name('cashbook.person');
    Route::post('/cashbook/person/{type}/{id}/send-statement', [CashbookController::class, 'sendStatement'])->name('cashbook.send-statement')->where('type', 'customer|supplier');

    // Staff
    Route::resource('staff', StaffController::class);
    Route::post('/staff/{employee}/salary', [SalaryController::class, 'store'])->name('staff.salary.store');

    // Reminders
    Route::post('/reminders/customer/{customer}', [App\Http\Controllers\ReminderController::class, 'sendCustomerReminder'])->name('reminders.customer');
    Route::post('/reminders/supplier/{supplier}', [App\Http\Controllers\ReminderController::class, 'sendSupplierReminder'])->name('reminders.supplier');
    Route::get('/reminders', [App\Http\Controllers\ReminderController::class, 'history'])->name('reminders.history');

    // WhatsApp chats (reminder messages in a WhatsApp-style UI)
    Route::get('/whatsapp-chats', [App\Http\Controllers\WhatsAppChatController::class, 'index'])->name('whatsapp.chats.index');
    Route::get('/whatsapp-chats/{type}/{id}', [App\Http\Controllers\WhatsAppChatController::class, 'show'])->name('whatsapp.chats.show')->where('type', 'customer|supplier');

    // Digital Passbook
    Route::get('/passbook', [App\Http\Controllers\PassbookController::class, 'index'])->name('passbook.index');

    // Spend Breakdown
    Route::get('/spend-breakdown', [App\Http\Controllers\SpendBreakdownController::class, 'index'])->name('spend-breakdown.index');

    // App Lock
    Route::get('/app-lock', [App\Http\Controllers\AppLockController::class, 'index'])->name('app-lock.index');
    Route::post('/app-lock/set-pin', [App\Http\Controllers\AppLockController::class, 'setPin'])->name('app-lock.set-pin');
    Route::delete('/app-lock/remove-pin', [App\Http\Controllers\AppLockController::class, 'removePin'])->name('app-lock.remove-pin');
    Route::post('/app-lock/toggle-biometric', [App\Http\Controllers\AppLockController::class, 'toggleBiometric'])->name('app-lock.toggle-biometric');
    Route::post('/app-lock/verify-pin', [App\Http\Controllers\AppLockController::class, 'verifyPin'])->name('app-lock.verify-pin')->middleware('throttle:5,1');
    Route::get('/lock', [App\Http\Controllers\AppLockController::class, 'lockScreen'])->name('app-lock.lock');

    // Onboarding
    Route::get('/onboarding', [App\Http\Controllers\OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/step1', [App\Http\Controllers\OnboardingController::class, 'storeStep1'])->name('onboarding.step1');
    Route::post('/onboarding/step2', [App\Http\Controllers\OnboardingController::class, 'storeStep2'])->name('onboarding.step2');
    Route::post('/onboarding/step3', [App\Http\Controllers\OnboardingController::class, 'storeStep3'])->name('onboarding.step3');
});