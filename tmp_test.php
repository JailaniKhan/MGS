<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Login as user
auth()->loginUsingId(1);

// Test SMS path (no API call, just returns drafted message)
$c = App\Models\Customer::whereNotNull('phone')->first();
echo "Customer: " . $c->name . " phone: " . $c->phone . "\n";

$rs = app(App\Services\Reminder\ReminderService::class);
$r = $rs->sendReminder(
    remindableType: 'customer',
    remindableId: $c->id,
    name: $c->name,
    phone: $c->phone,
    amount: '100',
    currency: 'AFN',
    channel: 'sms',
    dueDate: now()->addDays(7)->format('Y-m-d')
);

echo "STATUS: " . $r->status . "\n";
echo "MESSAGE: " . $r->message . "\n";
echo "PHONE: " . $r->phone . "\n";

// Test WhatsApp path (will skip because no whatsapp keys)
echo "\n--- WhatsApp test (will fail/no-api-key) ---\n";
$rw = $rs->sendReminder(
    remindableType: 'customer',
    remindableId: $c->id,
    name: $c->name,
    phone: $c->phone,
    amount: '100',
    currency: 'AFN',
    channel: 'whatsapp',
    dueDate: now()->addDays(7)->format('Y-m-d')
);
echo "WHATSAPP STATUS: " . $rw->status . "\n";
echo "WHATSAPP MSG: " . $rw->message . "\n";

// Test the route JSON output (simulate what the controller does)
echo "\n--- Simulating controller JSON response (SMS) ---\n";
echo json_encode([
    'phone' => $c->phone,
    'message' => $r->message,
    'status' => $r->status,
]) . "\n";