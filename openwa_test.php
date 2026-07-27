<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$manager = $app->make(\App\Services\WhatsApp\OpenWaManager::class);

echo "BEFORE: " . json_encode($manager->status(), JSON_PRETTY_PRINT) . "\n";

$ok = $manager->ensureStarted();

echo "ensureStarted() => " . var_export($ok, true) . "\n";
echo "AFTER:  " . json_encode($manager->status(), JSON_PRETTY_PRINT) . "\n";

$session = null;
try {
    $session = $app->make(\App\Services\WhatsApp\OpenWaService::class)->sessionStatus();
} catch (\Throwable $e) {
    $session = ['error' => $e->getMessage()];
}
echo "SESSION: " . json_encode($session, JSON_PRETTY_PRINT) . "\n";
