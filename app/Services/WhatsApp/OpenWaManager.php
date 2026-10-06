<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class OpenWaManager
{
    protected ?int $pid = null;

    private const PORT = 2785;

    /**
     * Cooldown state shared across requests. `php artisan serve` and the
     * NativePHP persistent runtime both reuse ONE PHP process for every
     * request, so statics survive between page loads. When the gateway is
     * down, every page that polls its status used to run the full launch
     * sequence (2×health probes + 2s wait + launch + 5s port wait) — up to
     * ~15-30s of blocking per page load. After a failed launch we now hold
     * a cooldown, so subsequent requests fail fast and report "down"
     * instead of re-attempting the whole launch dance.
     */
    private static ?float $lastLaunchAttemptAt = null;

    private static bool $lastLaunchSucceeded = false;

    /**
     * Human-readable description of the most recent launch failure (plus the
     * tail of the gateway's own log when available), so the Settings page can
     * show WHY the gateway is down instead of a bare "Stopped".
     */
    private static ?string $lastError = null;

    /** Seconds to wait before attempting another gateway launch. */
    private const LAUNCH_COOLDOWN_SECONDS = 15;

    /**
     * True when running inside a NativePHP Android app process.
     * NativePHP sets the `NATIVEPHP_PLATFORM` env var to `android` via
     * setenv() in the C++ bridge (php_bridge.c) before the Laravel
     * app boots, so config('nativephp-internal.platform') is reliable.
     */
    protected function isAndroid(): bool
    {
        // Prefer the NativePHP config value...
        $platform = config('nativephp-internal.platform');
        if ($platform) {
            return $platform === 'android';
        }
        // ...but fall back to a direct env check + a filesystem probe, in case
        // the NativePHP service provider hasn't published config yet (early
        // boot) or the app is running on a non-NativePHP Android shell.
        if (getenv('NATIVEPHP_PLATFORM') === 'android') {
            return true;
        }

        // Last-resort probe: real Android always has /system/build.prop.
        // We only check this on Linux (Windows can't have that path).
        return PHP_OS_FAMILY === 'Linux' && @is_file('/system/build.prop');
    }

    /**
     * True while a failed launch is still inside its cooldown window.
     * During cooldown we skip the expensive launch sequence entirely.
     */
    protected function launchInCooldown(): bool
    {
        if (self::$lastLaunchAttemptAt === null || self::$lastLaunchSucceeded) {
            return false;
        }

        return (microtime(true) - self::$lastLaunchAttemptAt) < self::LAUNCH_COOLDOWN_SECONDS;
    }

    protected function markLaunchAttempt(bool $succeeded): void
    {
        self::$lastLaunchAttemptAt = microtime(true);
        self::$lastLaunchSucceeded = $succeeded;
        if ($succeeded) {
            self::$lastError = null;
        }
    }

    /**
     * Remember the failure reason and write it (with the gateway's own log
     * tail, if available) to the Laravel log.
     */
    protected function recordError(string $message, ?string $logFile = null): void
    {
        $tail = $logFile ? $this->logTail($logFile) : '';
        self::$lastError = $message.($tail !== '' ? "\n\n--- openwa_app.log tail ---\n".$tail : '');
        Log::error('OpenWaManager: '.$message.($tail !== '' ? "\n".$tail : ''));
    }

    /**
     * Last N lines of a plain-text log file (gateway stdout/stderr or the
     * Baileys connection log). Reads only the trailing 64 KB so a large
     * log doesn't get slurped into memory on every status poll.
     */
    protected function logTail(string $file, int $maxLines = 40): string
    {
        if (! is_file($file)) {
            return '';
        }
        $size = @filesize($file);
        if ($size === false || $size === 0) {
            return '';
        }
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return '';
        }
        $chunk = 65536;
        if ($size > $chunk) {
            @fseek($handle, $size - $chunk);
        }
        $content = stream_get_contents($handle);
        @fclose($handle);
        if ($content === false) {
            return '';
        }
        $lines = array_values(array_filter(preg_split('/\r?\n/', $content), fn ($l) => trim((string) $l) !== ''));

        return trim(implode("\n", array_slice($lines, -$maxLines)));
    }

    public function ensureStarted(): bool
    {
        try {
            if (! $this->isAvailable()) {
                Log::debug('OpenWaManager: gateway not available, skipping');

                return false;
            }

            // A recent launch already failed — don't re-attempt the whole
            // sequence on every page load (it blocks the request for seconds).
            // Checked BEFORE the 3s health probe so cooldown requests are
            // near-instant.
            if ($this->launchInCooldown()) {
                Log::debug('OpenWaManager: gateway launch in cooldown, skipping');

                return false;
            }

            if ($this->gatewayResponds()) {
                // Gateway is already up (e.g. left running from a previous
                // launch, or started outside this process). launch() only
                // starts the session when it performs the initial start, so
                // make sure the configured session is alive here — otherwise
                // the QR flow never begins. startSession() is idempotent: it
                // first checks the current state and only POSTs /start when
                // the session is missing/inactive.
                $this->startSession();

                return true;
            }

            // The health probe failed, but something still owns the port.
            // Baileys crypto can block the event loop for a couple of seconds
            // during QR generation, so give the process one more chance
            // before declaring it dead — killing it here used to wipe the
            // in-memory session and force a fresh QR every poll cycle.
            $livePid = $this->findPidByPort(self::PORT);
            if ($livePid) {
                usleep(2_000_000);
                if ($this->gatewayResponds()) {
                    $this->startSession();

                    return true;
                }
                $this->killTree($livePid);
                usleep(500_000);
            }

            $this->ensureSymlinks();

            return $this->launch();
        } catch (\Throwable $e) {
            $this->recordError('error during startup: '.$e->getMessage());

            return false;
        }
    }

    public function start(): bool
    {
        return $this->ensureStarted();
    }

    protected function isAvailable(): bool
    {
        if (! $this->resolveAppDir()) {
            return false;
        }
        if (! $this->resolveNodeBinary()) {
            return false;
        }

        return true;
    }

    /**
     * On Android, the OpenWA bundle ships inside the APK at
     * `bootstrap/openwa/` (NOT under `storage/`, because NativePHP's Kotlin
     * `LaravelEnvironment.unzip()` skips every zip entry prefixed with
     * `storage/`). On first launch the files haven't been copied to the
     * runtime `storage_path('app/openwa/')` yet, so we deploy them here.
     *
     * This is idempotent: if the runtime copy already exists and contains
     * `server.js`, we skip the copy. A stale `bootstrap/` from a prior APK
     * version is always overwritten by the freshly-extracted one on each
     * new APK install, so re-deploys after an update pick up the new files.
     */
    protected function ensureBundleDeployed(): void
    {
        if (! $this->isAndroid()) {
            return;
        }

        $runtimeDir = storage_path('app/openwa');
        $bootstrapDir = base_path('bootstrap/openwa');

        // A `.bundle_version` marker at bootstrap/openwa/ is the source of
        // truth for what the current APK ships. The fast path below used to
        // skip the deploy entirely once server.js existed — which meant an
        // APK update with a fixed server.js never replaced the stale on-device
        // copy. Bump the marker (via the bundle tooling or manually) whenever
        // the bundled gateway changes, and the redeploy runs on next launch.
        $bundleVersion = trim((string) @file_get_contents("{$bootstrapDir}/.bundle_version"));
        $deployedVersion = trim((string) @file_get_contents("{$runtimeDir}/.bundle_version"));

        // Fast path: runtime copy already has the entry point AND matches the
        // shipped bundle version.
        if (is_file("{$runtimeDir}/app/server.js") && $deployedVersion !== '' && $deployedVersion === $bundleVersion) {
            return;
        }

        if (! is_dir($bootstrapDir)) {
            Log::warning('OpenWaManager: bootstrap/openwa not found — cannot deploy bundle', [
                'bootstrap' => $bootstrapDir,
                'runtime' => $runtimeDir,
            ]);

            return;
        }

        Log::info('OpenWaManager: deploying OpenWA bundle from bootstrap to runtime storage', [
            'from' => $bootstrapDir,
            'to' => $runtimeDir,
        ]);

        if (! is_dir($runtimeDir)) {
            @mkdir($runtimeDir, 0755, true);
        }
        @mkdir("{$runtimeDir}/app", 0755, true);
        @mkdir("{$runtimeDir}/node", 0755, true);
        @mkdir("{$runtimeDir}/modules", 0755, true);

        // Copy app/, node/, modules/ subdirs.
        foreach (['app', 'node', 'modules'] as $sub) {
            $src = "{$bootstrapDir}/{$sub}";
            $dst = "{$runtimeDir}/{$sub}";
            if (! is_dir($src)) {
                continue;
            }
            $this->rcopyDir($src, $dst);
        }

        // Make the node binary executable (Android's unzippers don't
        // preserve POSIX execute bits — only read/write).
        $nodeBin = "{$runtimeDir}/node/node";
        if (is_file($nodeBin)) {
            @chmod($nodeBin, 0755);
        }

        // Record the deployed bundle version so future launches fast-path.
        if ($bundleVersion !== '') {
            @file_put_contents("{$runtimeDir}/.bundle_version", $bundleVersion);
        }

        Log::info('OpenWaManager: bundle deployed', [
            'server_js' => is_file("{$runtimeDir}/app/server.js") ? 'present' : 'missing',
            'node_bin' => is_file($nodeBin) ? 'present' : 'missing',
            'so_files' => glob("{$runtimeDir}/node/lib/*.so*"),
        ]);
    }

    /**
     * Recursive directory copy that follows symlinks (the bundled `.so`
     * files are real files, not symlinks, so this is a plain recursive copy).
     */
    protected function rcopyDir(string $src, string $dst): void
    {
        if (! is_dir($src)) {
            return;
        }
        if (! is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = "{$src}/{$file}";
            $dstPath = "{$dst}/{$file}";
            if (is_dir($srcPath)) {
                $this->rcopyDir($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    protected function resolveAppDir(): ?string
    {
        // On Android, config('services.openwa.binary_dir') can only be a
        // dev-machine absolute path baked into the APK's embedded .env — it
        // can never exist on-device. Ignore it entirely and always use the
        // app-private runtime copy deployed from the bundled bootstrap/.
        if (! $this->isAndroid()) {
            $dir = config('services.openwa.binary_dir');
            if ($dir && is_dir($dir)) {
                return $dir;
            }
        }

        $this->ensureBundleDeployed();

        $storagePath = storage_path('app/openwa/app');
        if (is_dir($storagePath)) {
            return $storagePath;
        }

        return null;
    }

    protected function launch(): bool
    {
        $appDir = $this->resolveAppDir();
        if (! $appDir) {
            Log::info('OpenWaManager: binary_dir not available, skipping local start', ['dir' => config('services.openwa.binary_dir')]);
            $this->markLaunchAttempt(false);

            return false;
        }

        $mainJs = "{$appDir}/server.js";
        if (! file_exists($mainJs)) {
            $mainJs = "{$appDir}/dist/main.js";
        }
        if (! file_exists($mainJs)) {
            $mainJs = "{$appDir}/main.js";
        }
        if (! file_exists($mainJs)) {
            Log::info('OpenWaManager: no entry point found (tried server.js, dist/main.js, main.js), skipping', ['dir' => $appDir]);
            $this->markLaunchAttempt(false);

            return false;
        }

        $modulesDir = dirname($appDir).'/modules';
        $logFile = "{$appDir}/openwa_app.log";
        $pidFile = $this->pidFilePath();

        $nodeBin = $this->resolveNodeBinary();
        if ($nodeBin && is_file($nodeBin) && ! is_executable($nodeBin)) {
            @chmod($nodeBin, 0755);
        }
        if (! $nodeBin) {
            $this->recordError('no node binary found (resolveNodeBinary returned null)');
            $this->markLaunchAttempt(false);

            return false;
        }
        if (is_file($nodeBin) && ! is_executable($nodeBin)) {
            $this->recordError("node binary exists but is not executable: {$nodeBin}", $logFile);
            $this->markLaunchAttempt(false);

            return false;
        }

        Log::info('OpenWaManager: launching OpenWA gateway', [
            'node' => $nodeBin,
            'appDir' => $appDir,
            'mainJs' => $mainJs,
            'modules' => is_dir($modulesDir) ? $modulesDir : null,
        ]);

        $envVars = $this->buildEnvVars($appDir, $modulesDir);

        if ($this->isWindows()) {
            // Double-quote paths so Node in "C:\Program Files\..." works under cmd /c.
            $cmd = sprintf(
                'cd /D "%s" && %s start "OpenWA" /B cmd /c ""%s" "%s" >> "%s" 2>&1"',
                $appDir,
                $envVars,
                $nodeBin,
                $mainJs,
                $logFile
            );
            exec($cmd, $output, $exitCode);
        } elseif ($this->isAndroid()) {
            // On Android, /system/bin/sh may not have `nohup` and bg'ing with
            // `&` via exec() is unreliable across SELinux domains. Spawn the
            // process via proc_open() with STDIN/STDOUT/STDERR redirected to
            // the log file, then close the parent pipe — the child continues
            // and PHP gets the real PID via proc_get_status().
            $descriptors = [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $logFile, 'a'],
                2 => ['file', $logFile, 'a'],
            ];
            $envArray = [];
            // Parse $envVars ('KEY="value" KEY2="value2"') back into an array.
            if (preg_match_all('/(\w+)="([^"]*)"/', $envVars, $m, PREG_SET_ORDER)) {
                foreach ($m as $row) {
                    $envArray[$row[1]] = $row[2];
                }
            }
            $fullCmd = "{$envVars} \"{$nodeBin}\" \"{$mainJs}\"";
            $proc = @proc_open($fullCmd, $descriptors, $pipes, $appDir, $envArray);
            if (! is_resource($proc)) {
                $err = error_get_last();
                $this->recordError(
                    'proc_open failed on Android: '.($err['message'] ?? 'unknown error'),
                    $logFile
                );

                return false;
            }
            $status = proc_get_status($proc);
            if (isset($status['pid']) && $status['pid'] > 0) {
                $this->pid = (int) $status['pid'];
                file_put_contents($pidFile, $this->pid);
            }
            // Mark as long-running; proc_close would block — just release the
            // PHP-side handle (child survives independently of the parent).
            // On modern PHP via proc_open, the child detaches correctly when
            // stdin/stdout/stderr are bound to a file (not directly inherited).
            // We intentionally do *not* call proc_close here.
            $exitCode = 0;
            unset($proc);
        } else {
            $cmd = sprintf(
                'cd "%s" && %s nohup "%s" "%s" >> "%s" 2>&1 & echo $!',
                $appDir,
                $envVars,
                $nodeBin,
                $mainJs,
                $logFile
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && ! empty($output)) {
                $last = trim((string) end($output));
                if ($last && is_numeric($last)) {
                    $this->pid = (int) $last;
                    file_put_contents($pidFile, $this->pid);
                }
            }
        }

        if ($exitCode !== 0) {
            Log::error('OpenWaManager: exec failed to launch', ['exitCode' => $exitCode]);
            $this->markLaunchAttempt(false);

            return false;
        }

        if (! $this->isWindows()) {
            usleep(500_000);
        }

        $started = $this->waitForPort(self::PORT, 5);

        if ($started) {
            if ($this->isWindows() || ! $this->pid) {
                $this->pid = $this->findPidByPort(self::PORT);
            }
            if ($this->pid) {
                file_put_contents($pidFile, $this->pid);
            }
            Log::info('OpenWaManager: started successfully', ['pid' => $this->pid]);

            usleep(2_000_000);

            $sessionStarted = $this->startSession();
            if (! $sessionStarted) {
                Log::error('OpenWaManager: gateway started but session start failed');
            }
            $this->markLaunchAttempt(true);

            return $sessionStarted;
        }

        Log::error('OpenWaManager: started but port never came up', ['port' => self::PORT]);
        $this->recordError('started but port '.self::PORT.' never came up (node may have crashed on launch)', $logFile);
        $this->markLaunchAttempt(false);

        return false;
    }

    public function stop(): void
    {
        $pidFile = $this->pidFilePath();
        $targetPid = $this->readOwnPid();

        if (! $targetPid) {
            $targetPid = $this->findPidByPort(self::PORT);
        }

        if ($targetPid && $this->processAlive($targetPid)) {
            $this->killTree($targetPid);
            Log::info('OpenWaManager: stopped', ['pid' => $targetPid]);
        }

        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }
        $this->pid = null;
    }

    public function isRunning(): bool
    {
        // During launch cooldown the gateway is known to be down — return
        // immediately instead of spending 5s on port+HTTP probes per request.
        if ($this->launchInCooldown()) {
            return false;
        }

        return $this->checkPort(self::PORT, 2) && $this->gatewayResponds();
    }

    public function status(): array
    {
        $port = self::PORT;
        $running = $this->isRunning();
        $pid = $running ? ($this->findPidByPort($port) ?: $this->pid) : null;

        $appDir = $this->resolveAppDir();

        return [
            'running' => $running,
            'pid' => $pid,
            'port' => $port,
            'binary_dir' => config('services.openwa.binary_dir'),
            'node_binary' => config('services.openwa.node_binary'),
            'resolved_app_dir' => $appDir,
            'resolved_node_binary' => $this->resolveNodeBinary(),
            'last_error' => self::$lastError,
            // Baileys connection log (next to server.js): records every
            // "QR generated" / "CONNECTED" / "connection closed {reason}"
            // event. This is the key diagnostic when a scan or pairing code
            // "does nothing" — the reason the WS dropped (or the phone's
            // link response arrived) is right here.
            'baileys_log_tail' => $appDir ? $this->logTail("{$appDir}/baileys.log") : '',
        ];
    }

    protected function buildEnvVars(string $appDir, ?string $modulesDir): string
    {
        $vars = [
            'NODE_OPTIONS' => '--max-old-space-size=2048 --preserve-symlinks',
            'API_KEY' => config('services.openwa.api_key') ?? '',
            'PORT' => (string) self::PORT,
            'SESSION_DATA_PATH' => './data/sessions',
            'AUTO_START_SESSIONS' => 'true',
        ];

        // On Android, the Termux `node` binary needs LD_LIBRARY_PATH pointed
        // at the bundled Termux shared libs (libssl.so.3, libcrypto.so.3,
        // libicu*.so, libsqlite, c-ares, libc++). Without it exec() fails
        // with ENOENT because the dynamic linker can't resolve the .so deps.
        $libDir = dirname($this->resolveNodeBinary() ?? '').'/lib';
        if ($this->isAndroid() && is_dir($libDir)) {
            $existing = getenv('LD_LIBRARY_PATH');
            $vars['LD_LIBRARY_PATH'] = $existing ? "{$libDir}:{$existing}" : $libDir;
        }

        $result = [];
        foreach ($vars as $key => $value) {
            if ($this->isWindows()) {
                $result[] = sprintf('set "%s=%s" &&', $key, $value);
            } else {
                $result[] = sprintf('%s="%s"', $key, $value);
            }
        }

        return implode($this->isWindows() ? ' ' : ' ', $result);
    }

    protected function ensureSymlinks(): void
    {
        $appDir = $this->resolveAppDir();
        if (! $appDir) {
            return;
        }

        $nodeModules = "{$appDir}/node_modules";
        $modulesDir = dirname($appDir).'/modules';

        if (! is_dir($modulesDir)) {
            return;
        }
        if (is_link($nodeModules) || is_dir($nodeModules)) {
            return;
        }

        if ($this->isWindows()) {
            exec(sprintf('mklink /J "%s" "%s" 2>NUL', $nodeModules, $modulesDir), $out, $code);
        } else {
            @symlink($modulesDir, $nodeModules);
        }
    }

    public function restart(): bool
    {
        $this->stop();
        usleep(1_000_000);

        return $this->launch();
    }

    public function startSession(): bool
    {
        $sessionId = config('services.openwa.session');
        $apiKey = config('services.openwa.api_key');
        if (! $sessionId || ! $apiKey) {
            return false;
        }

        $status = $this->http('GET', "/api/sessions/{$sessionId}", 5);
        if ($status && $status['status'] === 200) {
            $currentStatus = $status['json']['status'] ?? null;
            // qr_ready must count as active — POSTing /start while a QR is
            // waiting would kill the socket and rotate the QR again.
            // disconnected must NOT count as active: when the QR expires
            // without a scan the session sits at disconnected with no QR,
            // and the only way back to a fresh QR is a manual kick (Refresh).
            if (in_array($currentStatus, ['ready', 'connected', 'connecting', 'initializing', 'qr_ready'], true)) {
                Log::info('OpenWaManager: session already active', [
                    'session' => $sessionId,
                    'status' => $currentStatus,
                ]);

                return true;
            }
        }

        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resp = $this->http('POST', "/api/sessions/{$sessionId}/start", 10);

            if ($resp && $resp['status'] === 200) {
                Log::info('OpenWaManager: session start initiated', [
                    'session' => $sessionId,
                    'attempt' => $attempt,
                ]);

                return true;
            }

            Log::warning('OpenWaManager: session start attempt failed', [
                'attempt' => $attempt,
                'status' => $resp['status'] ?? null,
                'body' => $resp['body'] ?? null,
            ]);

            if ($attempt < $maxAttempts) {
                usleep(3_000_000);
            }
        }

        return false;
    }

    protected function resolveNodeBinary(): ?string
    {
        $windows = $this->isWindows();

        // On Android, a configured node_binary path can only have been baked
        // in from the build machine (e.g. "D:\Mobile App\..."). It will never
        // be valid on-device, so skip it and always resolve from storage/.
        $configured = $this->isAndroid() ? null : config('services.openwa.node_binary');
        if ($configured && file_exists($configured) && is_executable($configured)) {
            return $configured;
        }

        if ($configured && file_exists($configured) && ! $windows) {
            @chmod($configured, 0755);
            if (is_executable($configured)) {
                return $configured;
            }
        }

        // On Android the bundle lives under bootstrap/openwa/ until the
        // first-launch migration copies it to the runtime storage dir.
        if (! $windows) {
            $this->ensureBundleDeployed();
        }

        // The bundled runtime at storage/app/openwa/node/node is the
        // cross-compiled android-arm64 Termux ELF — it can only run on
        // Android (or a matching Linux host). On Windows it would fail with
        // "not recognized as an internal or external command", so only the
        // Windows build (node.exe) is acceptable there.
        $storageNode = $windows
            ? storage_path('app/openwa/node/node.exe')
            : storage_path('app/openwa/node/node');
        if (file_exists($storageNode)) {
            if (! $windows) {
                @chmod($storageNode, 0755);
            }

            return $storageNode;
        }

        $appDir = config('services.openwa.binary_dir') ?: storage_path('app/openwa/app');
        if ($appDir) {
            $candidates = $windows
                ? [
                    "{$appDir}/../node/node.exe",
                    "{$appDir}/node.exe",
                ]
                : [
                    "{$appDir}/../node/node",
                    "{$appDir}/../node/node.exe",
                    "{$appDir}/node",
                    "{$appDir}/node.exe",
                    dirname($appDir).'/node/node',
                    dirname($appDir).'/node/node.exe',
                ];
            foreach ($candidates as $candidate) {
                $normalized = realpath($candidate) ?: $candidate;
                if (file_exists($normalized)) {
                    if (! $windows && ! is_executable($normalized)) {
                        @chmod($normalized, 0755);
                    }

                    return $normalized;
                }
            }
        }

        $which = $this->isWindows() ? 'where node 2>NUL' : 'command -v node 2>/dev/null';
        exec($which, $pathOut, $code);
        if ($code === 0 && ! empty($pathOut[0])) {
            return trim($pathOut[0]);
        }

        return null;
    }

    protected function checkPort(int $port, float $timeout = 2): bool
    {
        try {
            $c = @fsockopen('127.0.0.1', $port, $errno, $errstr, $timeout);
            if ($c) {
                fclose($c);

                return true;
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    protected function waitForPort(int $port, int $maxSeconds = 30): bool
    {
        for ($i = 0; $i < $maxSeconds; $i++) {
            if ($this->checkPort($port, 1)) {
                return true;
            }
            usleep(1_000_000);
        }

        return false;
    }

    protected function findPidByPort(int $port): ?int
    {
        if ($this->isWindows()) {
            return $this->findPidByPortWindows($port);
        }

        return $this->findPidByPortUnix($port);
    }

    protected function findPidByPortWindows(int $port): ?int
    {
        $output = [];
        exec('netstat -ano', $output, $exitCode);

        $patterns = [
            "/\\b0\\.0\\.0\\.0:{$port}\\b/",
            "/\\b127\\.0\\.0\\.1:{$port}\\b/",
            "/\\[::\\]:{$port}\\b/",
            "/\\[::1\\]:{$port}\\b/",
            "/\\b:::{$port}\\b/",
        ];

        foreach ($output as $line) {
            $line = trim($line);
            if (stripos($line, 'LISTENING') === false) {
                continue;
            }
            foreach ($patterns as $p) {
                if (preg_match($p, $line) && preg_match('/\\s+(\\d+)\\s*$/', $line, $m)) {
                    return (int) $m[1];
                }
            }
        }
        foreach ($output as $line) {
            $line = trim($line);
            foreach ($patterns as $p) {
                if (preg_match($p, $line) && preg_match('/\\s+(\\d+)\\s*$/', $line, $m)) {
                    return (int) $m[1];
                }
            }
        }

        return null;
    }

    protected function findPidByPortUnix(int $port): ?int
    {
        $output = [];
        exec("lsof -ti :{$port} 2>/dev/null", $output, $code);
        if ($code === 0 && ! empty($output)) {
            return (int) trim($output[0]);
        }

        $output = [];
        exec("ss -tlnp sport = :{$port} 2>/dev/null", $output, $code);
        if ($code === 0) {
            foreach ($output as $line) {
                if (preg_match('/pid=(\d+)/', $line, $m)) {
                    return (int) $m[1];
                }
            }
        }

        $output = [];
        @exec("fuser {$port}/tcp 2>/dev/null", $output, $code);
        if ($code === 0 && ! empty($output)) {
            $parts = preg_split('/\s+/', trim($output[0]));
            if (! empty($parts)) {
                return (int) end($parts);
            }
        }

        // Android ships none of lsof / ss / fuser. Fall back to scanning
        // /proc/net/tcp (and tcp6) for the listening socket's inode, then
        // walking /proc/*/fd/ to find the PID that owns that socket inode.
        // This is pure POSIX file I/O and works on stock Android.
        $inode = $this->findListeningSocketInode($port);
        if ($inode) {
            $pid = $this->findPidBySocketInode($inode);
            if ($pid) {
                return $pid;
            }
        }

        return null;
    }

    /**
     * Parse /proc/net/tcp (and tcp6) for a LISTEN socket on the given port.
     * Returns the socket's inode, or null if not found.
     *
     * Each /proc/net/tcp line looks like:
     *   sl  local_address rem_address   st tx_queue ... inode
     *    0: 0100007F:0AE1 00000000:0000 0A ...         12345
     * where 0AE1 is the port in hex (little-endian) and 0A = TCP_LISTEN.
     */
    protected function findListeningSocketInode(int $port): ?int
    {
        $listenState = '0A';
        $portHex = str_pad(dechex($port), 4, '0', STR_PAD_LEFT);
        // The local_address field uses big-endian port but we'll match
        // both formats to be safe across kernels.
        $sought = ['0100007F:'.$portHex, '00000000:'.$portHex];

        foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $procFile) {
            if (! @is_readable($procFile)) {
                continue;
            }
            $lines = @file($procFile);
            if (! $lines) {
                continue;
            }
            // Skip header row.
            for ($i = 1; $i < count($lines); $i++) {
                $parts = preg_split('/\s+/', trim($lines[$i]));
                if (count($parts) < 10) {
                    continue;
                }
                $local = $parts[1];
                $state = $parts[3];
                $inode = $parts[9] ?? null;
                if ($state !== $listenState) {
                    continue;
                }
                // Match either 127.0.0.1 or 0.0.0.0 binding, both little and big endian.
                $matches = false;
                foreach ($sought as $s) {
                    if (stripos($local, $s) !== false) {
                        $matches = true;
                        break;
                    }
                }
                if (! $matches) {
                    continue;
                }
                if ($inode && is_numeric($inode)) {
                    return (int) $inode;
                }
            }
        }

        return null;
    }

    /**
     * Walk /proc/PID/fd/ looking for a socket symlink matching the given
     * inode. Returns the PID, or null. Works on Linux and stock Android (no
     * shell tools required).
     */
    protected function findPidBySocketInode(int $inode): ?int
    {
        if ($inode <= 0 || ! is_dir('/proc')) {
            return null;
        }
        $target = "socket:[{$inode}]";
        foreach (scandir('/proc') as $entry) {
            if (! ctype_digit($entry)) {
                continue;
            }
            $fdDir = "/proc/{$entry}/fd";
            if (! is_dir($fdDir)) {
                continue;
            }
            $fds = @scandir($fdDir);
            if (! $fds) {
                continue;
            }
            foreach ($fds as $fd) {
                if ($fd === '.' || $fd === '..') {
                    continue;
                }
                $link = @readlink("{$fdDir}/{$fd}");
                if ($link === $target) {
                    return (int) $entry;
                }
            }
        }

        return null;
    }

    protected function processAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if ($this->isWindows()) {
            exec("tasklist /FI \"PID eq {$pid}\" /NH 2>NUL", $out, $code);
            foreach ((array) $out as $line) {
                if (stripos($line, (string) $pid) !== false) {
                    return true;
                }
            }

            return false;
        }

        if (is_dir("/proc/{$pid}")) {
            return true;
        }

        exec("kill -0 {$pid} 2>/dev/null", $out, $code);

        return $code === 0;
    }

    protected function killTree(int $pid): void
    {
        if ($this->isWindows()) {
            exec("taskkill /PID {$pid} /T /F 2>NUL", $o, $code);

            return;
        }

        // Recursively kill children. Try `pgrep -P` first (available on most
        // Linux/macOS); if it fails (Android, minimal BusyBox), fall back to
        // scanning /proc/<pid>/task/<tid>/children which the kernel populates
        // when CONFIG_SCHED_CHILDAGEMENT is enabled — and to walking
        // /proc/*/stat for processes whose ppid matches ours, which works
        // universally on any Linux/Android kernel.
        $children = [];
        exec("pgrep -P {$pid} 2>/dev/null", $children, $code);
        if ($code !== 0) {
            $children = $this->findChildPidsViaProc($pid);
        }
        foreach ($children as $child) {
            $child = (int) trim((string) $child);
            if ($child > 0) {
                $this->killTree($child);
            }
        }

        // Prefer posix_kill (no shell) on non-Windows so we don't depend on
        // the `kill` binary existing on the host. The `posix` extension is
        // enabled by default on most PHP builds; we check function_exists.
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 9);
        } else {
            exec("kill -9 {$pid} 2>/dev/null", $o, $code);
        }
    }

    /**
     * Find child PIDs of the given parent by walking /proc, without shelling
     * out to `pgrep`. Each /proc/<pid>/stat file's 4th whitespace-separated
     * field is the ppid. Works on stock Android (no userspace `pgrep`).
     *
     * @return int[]
     */
    protected function findChildPidsViaProc(int $ppid): array
    {
        if ($ppid <= 0 || ! is_dir('/proc')) {
            return [];
        }
        $children = [];
        foreach (scandir('/proc') as $entry) {
            if (! ctype_digit($entry)) {
                continue;
            }
            $statPath = "/proc/{$entry}/stat";
            if (! is_readable($statPath)) {
                continue;
            }
            $stat = @file_get_contents($statPath);
            if ($stat === false) {
                continue;
            }
            // stat fields are space-separated, but the comm (field 2) may
            // contain spaces inside parens, e.g. `(node)` or `(bash)`.
            // Pop everything after the last ')' to skip comm safely.
            $close = strrpos($stat, ')');
            if ($close === false) {
                continue;
            }
            $rest = substr($stat, $close + 2);
            $fields = explode(' ', $rest);
            // After comm, fields are state (1), ppid (2)... but $rest starts
            // at the field right after `)`+space which is state. So ppid is
            // $fields[1].
            $parentPid = $fields[1] ?? '';
            if ((int) $parentPid === $ppid) {
                $children[] = (int) $entry;
            }
        }

        return $children;
    }

    protected function pidFilePath(): string
    {
        return storage_path('app/openwa.pid');
    }

    protected function readOwnPid(): ?int
    {
        $pidFile = $this->pidFilePath();
        if (! file_exists($pidFile)) {
            return null;
        }
        $pid = (int) trim((string) file_get_contents($pidFile));

        return $pid > 0 ? $pid : null;
    }

    protected function writePid(?int $pid): void
    {
        if ($pid && $pid > 0) {
            file_put_contents($this->pidFilePath(), $pid);
        }
    }

    protected function gatewayResponds(): bool
    {
        // 3s timeout: the gateway's single-threaded event loop is busy with
        // Baileys crypto while the QR rotates, so a 1s limit caused false
        // "down" verdicts → the manager killed a perfectly healthy process.
        $resp = $this->http('GET', '/api/health', 3);
        if ($resp && in_array($resp['status'], [200, 401, 403, 404], true)) {
            return true;
        }

        return false;
    }

    protected function http(string $method, string $path, int $timeoutSec = 3): ?array
    {
        $base = 'http://127.0.0.1:'.self::PORT;
        $url = $base.$path;
        $apiKey = config('services.openwa.api_key');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT_MS => $timeoutSec * 1000,
            // Connect timeout must stay SHORT: Windows connects to a closed
            // localhost port time out rather than fail fast, so 2000ms here
            // burned 2 full seconds on every down-gateway probe (curl logged
            // "Connection timed out after 2002 ms" per attempt). A local
            // socket that is up answers in single-digit ms. The RESPONSE
            // timeout above (3s for a busy Baileys event loop) is untouched.
            CURLOPT_CONNECTTIMEOUT_MS => 750,
            CURLOPT_HTTPHEADER => ['X-API-Key: '.$apiKey, 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        unset($ch);

        if ($err || $body === false) {
            Log::debug('OpenWaManager http: curl error', ['url' => $url, 'err' => $err]);

            return null;
        }

        return ['status' => (int) $status, 'body' => $body, 'json' => json_decode($body, true)];
    }

    protected function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
