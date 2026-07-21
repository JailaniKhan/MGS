<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "BOOT OK\n";
echo "User uses HasApiTokens: " . (in_array('Laravel\Sanctum\HasApiTokens', class_uses_recursive(\App\Models\User::class)) ? 'YES' : 'NO') . "\n";
echo "GoogleController class_exists: " . (class_exists(\App\Http\Controllers\Api\V1\GoogleController::class) ? 'YES' : 'NO') . "\n";

// Check Google socialite driver availability
try {
    Socialite::driver('google')->scopes(['openid']);
    echo "Socialite google driver: OK\n";
} catch (\Throwable $e) {
    echo "Socialite google driver ERROR: " . $e->getMessage() . "\n";
}

// Check google_id column exists
$schema = \Illuminate\Support\Facades\Schema::getColumnListing('users');
echo "users columns google_id present: " . (in_array('google_id', $schema) ? 'YES' : 'NO') . "\n";
echo "users columns avatar present: " . (in_array('avatar', $schema) ? 'YES' : 'NO') . "\n";

// Check services config
$g = config('services.google');
echo "services.google configured: " . (is_array($g) && !empty($g['client_id']) ? 'YES (client_id set)' : 'NO (missing client_id)') . "\n";
