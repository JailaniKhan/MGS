<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * native:install mirrors the vendor stub over nativephp/android (robocopy
 * /MIR), which silently wipes every hand-applied Kotlin fix — and the
 * nativephp/ tree is gitignored, so those fixes live nowhere else. This
 * command re-applies the tracked copies from native-patches/ and must run
 * after every native:install and before every native:package.
 */
class NativePatch extends Command
{
    protected $signature = 'native:patch';

    protected $description = 'Re-apply tracked native Kotlin patches (native-patches/) to the nativephp/android build';

    protected array $files = [
        'app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt',
        'app/src/main/java/com/nativephp/mobile/bridge/PHPBridge.kt',
        'app/src/main/java/com/nativephp/mobile/bridge/BridgeFunctionRegistration.kt',
        'app/src/main/java/com/nativephp/mobile/bridge/functions/PrintFunctions.kt',
    ];

    public function handle(): int
    {
        $targetBase = base_path('nativephp/android');

        if (! is_dir($targetBase)) {
            $this->error('nativephp/android not found — run native:install first.');

            return self::FAILURE;
        }

        foreach ($this->files as $relative) {
            $from = base_path('native-patches/'.$relative);
            $to = $targetBase.'/'.$relative;

            if (! is_file($from)) {
                $this->error("Patch source missing: {$from}");

                return self::FAILURE;
            }

            if (! is_dir(dirname($to))) {
                mkdir(dirname($to), 0777, true);
            }

            copy($from, $to);
            $this->line("  ✓ {$relative}");
        }

        $this->info('Native patches applied ('.count($this->files).' files).');

        return self::SUCCESS;
    }
}
