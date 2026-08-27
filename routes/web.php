<?php

use App\Http\Controllers\AppLockController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CashbookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\PassbookController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpendBreakdownController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WhatsAppChatController;
use App\Http\Controllers\WhatsAppMessageController;
use Illuminate\Support\Facades\Route;

// Auth routes (no auth middleware)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/login/phone', function () {
    return view('auth.otp-login');
})->name('login.phone');
Route::post('/login/phone/verify', [AuthController::class, 'verifyPhoneOtp'])->name('login.phone.verify')->middleware('throttle:5,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Forgot / reset password (public — a guest must reach these without being logged in).
Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetCode'])->name('password.email')->middleware('throttle:5,1');
Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:5,1');

// Language preference must be switchable from the guest auth pages too.
// Guests only ever touch their own session (see SettingsController), but the
// endpoint is still public — keep it throttled against abuse.
Route::post('/language', [SettingsController::class, 'updateLanguage'])->name('language.update')->middleware('throttle:10,1');

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/openwa', [SettingsController::class, 'openwa'])->name('settings.openwa');
    Route::get('/settings/openwa/qr', [SettingsController::class, 'openwaQr'])->name('settings.openwa.qr');
    Route::post('/settings/openwa/pairing-code', [SettingsController::class, 'openwaPairingCode'])->name('settings.openwa.pairing');
    Route::get('/settings/openwa/pairing-status', [SettingsController::class, 'openwaPairingStatus'])->name('settings.openwa.pairing-status');
    Route::post('/settings/openwa/test-send', [SettingsController::class, 'openwaTestSend'])->name('settings.openwa.test');
    Route::post('/settings/openwa/restart', [SettingsController::class, 'openwaRestart'])->name('settings.openwa.restart');

    Route::get('/people', [PeopleController::class, 'index'])->name('people.index');
    Route::get('/transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::get('/ledger/{type}/{id}', [LedgerController::class, 'show'])->name('ledger.show');
    Route::get('/ledger/{type}/{id}/pdf', [LedgerController::class, 'downloadPdf'])->name('ledger.pdf');
    Route::post('/ledger/{type}/{id}/payment', [LedgerController::class, 'paymentStore'])->name('ledger.payment.store');

    Route::resource('customers', CustomerController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('expenses', ExpenseController::class);
    Route::resource('products', ProductController::class);
    Route::resource('units', UnitController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('purchases', PurchaseController::class)->except(['edit', 'update']);
    Route::post('/purchases/{purchase}/status/{status}', [PurchaseController::class, 'status'])->name('purchases.status');
    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
    Route::post('/purchases/{purchase}/send-whatsapp', [PurchaseController::class, 'sendWhatsApp'])->name('purchases.send-whatsapp');
    Route::resource('orders', OrderController::class)->except(['show']);

    Route::get('/orders/{order}/show', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/print', [OrderController::class, 'print'])->name('orders.print');
    Route::post('/orders/{order}/send-whatsapp', [OrderController::class, 'sendWhatsApp'])->name('orders.send-whatsapp');
    Route::post('/orders/{order}/status/{status}', [OrderController::class, 'status'])->name('orders.status');

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
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/stock', [ReportController::class, 'stockReport'])->name('reports.stock');
    Route::get('/reports/daybook', [ReportController::class, 'daybook'])->name('reports.daybook');
    Route::get('/reports/aging', [ReportController::class, 'aging'])->name('reports.aging');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/customers', [PaymentController::class, 'customerPaymentsPage'])->name('payments.customers');
    // JSON feed for the customers page — lives on the session-authed web
    // group because its only consumer is a Blade page (no bearer token).
    Route::get('/payments-data', [PaymentController::class, 'customerIndex'])->name('payments.data');
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
    Route::resource('staff', StaffController::class)->parameters(['staff' => 'employee']);
    Route::post('/staff/{employee}/salary', [SalaryController::class, 'store'])->name('staff.salary.store');

    // Reminders
    Route::post('/reminders/customer/{customer}', [ReminderController::class, 'sendCustomerReminder'])->name('reminders.customer');
    Route::post('/reminders/supplier/{supplier}', [ReminderController::class, 'sendSupplierReminder'])->name('reminders.supplier');
    Route::get('/reminders', [ReminderController::class, 'history'])->name('reminders.history');

    // WhatsApp chats (reminder messages in a WhatsApp-style UI)
    Route::get('/whatsapp-chats', [WhatsAppChatController::class, 'index'])->name('whatsapp.chats.index');
    Route::get('/whatsapp-chats/{type}/{id}', [WhatsAppChatController::class, 'show'])->name('whatsapp.chats.show')->where('type', 'customer|supplier');

    // Free-form messages (custom text / voice notes) from the chats hub
    Route::post('/customers/{customer}/message', [WhatsAppMessageController::class, 'storeCustomer'])->name('customers.message');
    Route::post('/suppliers/{supplier}/message', [WhatsAppMessageController::class, 'storeSupplier'])->name('suppliers.message');
    Route::get('/whatsapp-media/{reminder}', [WhatsAppMessageController::class, 'media'])->name('whatsapp.chats.media');

    // Digital Passbook
    Route::get('/passbook', [PassbookController::class, 'index'])->name('passbook.index');

    // Spend Breakdown
    Route::get('/spend-breakdown', [SpendBreakdownController::class, 'index'])->name('spend-breakdown.index');

    // App Lock
    Route::get('/app-lock', [AppLockController::class, 'index'])->name('app-lock.index');
    Route::post('/app-lock/set-pin', [AppLockController::class, 'setPin'])->name('app-lock.set-pin');
    Route::delete('/app-lock/remove-pin', [AppLockController::class, 'removePin'])->name('app-lock.remove-pin');
    Route::post('/app-lock/toggle-biometric', [AppLockController::class, 'toggleBiometric'])->name('app-lock.toggle-biometric');
    Route::post('/app-lock/verify-pin', [AppLockController::class, 'verifyPin'])->name('app-lock.verify-pin')->middleware('throttle:5,1');
    Route::get('/lock', [AppLockController::class, 'lockScreen'])->name('app-lock.lock');

    // Onboarding
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1');
    Route::post('/onboarding/step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2');
    Route::post('/onboarding/step3', [OnboardingController::class, 'storeStep3'])->name('onboarding.step3');
});
