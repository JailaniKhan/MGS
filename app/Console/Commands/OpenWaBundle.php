<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OpenWaBundle extends Command
{
    protected $signature = 'openwa:bundle
        {--target=android-arm64 : Target APK platform (android-arm64, android-x86_64, android-x86)}
        {--node-version= : Node.js version (defaults to Termux nodejs-lts 24.18.0 for Android)}';

    protected $description = 'Prepare bundled Node.js + OpenWA for the NativePHP APK build';

    public function handle(): int
    {
        $target = $this->option('target');
        $nodeVersion = $this->option('node-version') ?: (str_starts_with($target, 'android-') ? '24.18.0' : '22.14.0');
        $runtimeDir = storage_path('app/openwa');
        $buildDir = "{$runtimeDir}/build";

        $this->components->twoColumnDetail('Target', $target);
        $this->components->twoColumnDetail('Node.js', $nodeVersion);

        if (!is_dir($runtimeDir)) {
            $this->error("OpenWA runtime not found at {$runtimeDir}");
            $this->warn('Run "php artisan openwa:install" first.');
            return self::FAILURE;
        }

        if (is_dir($buildDir)) {
            $this->components->task('Cleaning previous build', fn() => $this->rrmdir($buildDir));
        }

        mkdir($buildDir, 0755, true);
        mkdir("{$buildDir}/node", 0755, true);
        mkdir("{$buildDir}/app", 0755, true);
        mkdir("{$buildDir}/modules", 0755, true);

        // Download Node.js for target platform
        $nodeOk = $this->downloadNodeForAndroid($target, $nodeVersion, $buildDir);
        if (!$nodeOk) {
            return self::FAILURE;
        }

        // Copy app files (lightweight gateway or full OpenWA)
        $appDir = "{$runtimeDir}/app";
        if (is_dir($appDir)) {
            $this->components->task('Copying gateway app', function () use ($appDir, $buildDir) {
                $this->rcopy($appDir, "{$buildDir}/app");
                return true;
            });
        } else {
            $this->warn('Gateway app not found. Installing lightweight gateway...');
            $this->call('openwa:install', ['--lightweight', '--target' => $target, '--force' => true]);
        }

        // Generate .env for the app
        $apiKey = config('services.openwa.api_key');
        if (!$apiKey) {
            $apiKey = 'mgs_' . bin2hex(random_bytes(24));
            $this->warn("OPENWA_API_KEY not set, generated: {$apiKey}");
            $this->warn('Set OPENWA_API_KEY in your .env for consistency across builds.');
        }
        $this->generateAppEnv("{$buildDir}/app", $apiKey);

        // Copy npm modules (renamed from node_modules)
        $modulesDir = "{$runtimeDir}/modules";
        if (is_dir($modulesDir)) {
            $this->components->task('Copying npm modules', function () use ($modulesDir, $buildDir) {
                $this->rcopy($modulesDir, "{$buildDir}/modules");
                return true;
            });
        }

        // Verify the bundle
        $missing = [];
        if (!is_file("{$buildDir}/node/node")) {
            $missing[] = 'node/node (Node.js binary)';
        }
        if (!is_file("{$buildDir}/app/package.json")) {
            $missing[] = 'app/package.json (OpenWA app)';
        }
        if (!is_dir("{$buildDir}/modules")) {
            $missing[] = 'modules/ (npm dependencies)';
        }

        if (!empty($missing)) {
            $this->newLine();
            $this->error('Bundle is incomplete — missing:');
            foreach ($missing as $item) {
                $this->line("  - {$item}");
            }
            return self::FAILURE;
        }

        // Generate a config file for the APK. We deliberately leave OPENWA_BINARY_DIR
        // and OPENWA_NODE_BINARY unset — OpenWaManager resolves them automatically
        // via storage_path('app/openwa/...') which correctly resolves to the
        // NativePHP app-private storage path on Android. The previous
        // `{{APP_DIR}}` placeholders were never substituted by anything and
        // caused file_exists() to silently fail on-device.
        $config = [
            'OPENWA_BASE_URL' => 'http://127.0.0.1:2785',
            'OPENWA_API_KEY' => $apiKey,
            'OPENWA_SESSION' => 'default',
            'OPENWA_AUTO_START' => 'true',
            '# OPENWA_BINARY_DIR' => 'auto-resolved by OpenWaManager to storage/app/openwa/app',
            '# OPENWA_NODE_BINARY' => 'auto-resolved by OpenWaManager to storage/app/openwa/node/node',
        ];

        file_put_contents(
            "{$buildDir}/openwa.env",
            "# OpenWA Bundle Config — merge these into your .env or nativephp.php\n" .
            "# The OPENWA_*_DIR keys are intentionally commented out:\n" .
            "# OpenWaManager picks them up from storage_path() automatically on-device.\n" .
            implode("\n", array_map(fn($k, $v) => "{$k}={$v}", array_keys($config), $config))
        );

        $this->newLine();

        $this->components->twoColumnDetail('Bundle size', $this->formatSize($this->dirSize($buildDir)));
        $this->components->twoColumnDetail('Node.js', is_file("{$buildDir}/node/node") ? 'ready' : 'missing');
        $this->components->twoColumnDetail('OpenWA app', is_file("{$buildDir}/app/package.json") ? 'ready' : 'missing');
        $this->components->twoColumnDetail('npm modules', is_dir("{$buildDir}/modules") ? 'ready' : 'missing');

        $this->newLine();
        $this->components->success('OpenWA bundle ready for APK build!');
        $this->line("  Bundle location: {$buildDir}");
        $this->line("  Config file:     {$buildDir}/openwa.env");
        $this->newLine();
        $this->warn('These files are automatically included in the APK via the NativePHP bundle process (they live under storage/app/).');
        $this->warn('On first launch, the node binary will be made executable automatically by OpenWaManager.');

        return self::SUCCESS;
    }

    protected function downloadNodeForAndroid(string $target, string $version, string $buildDir): bool
    {
        // Map our target name to Termux's arch filename suffix.
        $archMap = [
            'android-arm64'  => 'aarch64',
            'android-x86_64' => 'x86_64',
            'android-x86'    => 'i686',
            'android-arm'    => 'arm',
        ];

        $arch = $archMap[$target] ?? null;
        if (!$arch) {
            $this->error("Unknown target: {$target}");
            return false;
        }

        $dest = "{$buildDir}/node/node";
        $libDir = "{$buildDir}/node/lib";

        if (is_file($dest) && is_file("{$libDir}/libssl.so.3")) {
            $this->components->twoColumnDetail('Node.js binary', 'already cached');
            return true;
        }

        // Termux nodejs-lts .deb package (Bionic-compiled — runs on stock Android).
        $url = "https://packages.termux.dev/apt/termux-main/pool/main/n/nodejs-lts/nodejs-lts_{$version}_{$arch}.deb";
        $archive = storage_path("app/openwa/downloads/termux-nodejs-lts_{$version}_{$arch}.deb");

        if (!is_file($archive)) {
            $this->components->task("Downloading Termux Node.js {$version} ({$arch})", function () use ($url, $archive) {
                $dir = dirname($archive);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                return $this->download($url, $archive);
            });
        } else {
            $this->components->twoColumnDetail('Archive', 'already downloaded');
        }

        $ok = $this->components->task('Extracting Node.js binary', function () use ($archive, $buildDir) {
            $tmp = storage_path('app/openwa/downloads/_node_extract_bundle');
            if (is_dir($tmp)) $this->rrmdir($tmp);
            mkdir($tmp, 0755, true);

            $ok = $this->extractDebInto($archive, $tmp);
            if (!$ok) {
                $this->error("Deb extraction failed for {$archive}");
                return false;
            }

            // Locate `node` binary inside the extracted Termux tree.
            $nodeBin = $this->findFileRecursive($tmp, 'node');
            if (!$nodeBin) {
                $this->error('node binary not found inside extracted .deb');
                return false;
            }

            if (!is_dir("{$buildDir}/node")) {
                mkdir("{$buildDir}/node", 0755, true);
            }
            copy($nodeBin, $dest);
            @chmod($dest, 0755);

            // Also copy any .so files shipped in the node deb itself.
            $usrLibDir = dirname($nodeBin) . '/../lib';
            if (is_dir($usrLibDir)) {
                $realLibDir = realpath($usrLibDir);
                if ($realLibDir && is_dir($realLibDir)) {
                    if (!is_dir($libDir)) {
                        mkdir($libDir, 0755, true);
                    }
                    foreach (scandir($realLibDir) as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        if (!str_ends_with($entry, '.so') && !preg_match('/\.so\.\d+/', $entry)) continue;
                        copy("{$realLibDir}/{$entry}", "{$libDir}/{$entry}");
                    }
                }
            }

            return true;
        });

        if (!$ok || !is_file($dest)) {
            $this->error('Failed to extract Node.js binary');
            return false;
        }

        // Termux `node` depends on bundled libs from other packages.
        // Download + extract them into the APK's node/lib/ directory.
        $this->components->task('Downloading Termux dependency libs', function () use ($arch, $libDir) {
            if (!is_dir($libDir)) mkdir($libDir, 0755, true);
            $urls = $this->termuxDependencyUrls($arch);
            foreach ($urls as $name => $debUrl) {
                $debName = basename(parse_url($debUrl, PHP_URL_PATH));
                $debPath = storage_path("app/openwa/downloads/termux-{$debName}");

                if (!is_file($debPath)) {
                    $downloaded = $this->download($debUrl, $debPath);
                    if (!$downloaded) {
                        $this->warn("Failed to download Termux {$name}; node may fail to start.");
                        continue;
                    }
                }
                $this->extractDebInto($debPath, $libDir);
            }
            return true;
        });

        // Files extracted from the Termux deb end up under
        // `libDir/data/data/com.termux/files/usr/lib/*.so`. Flatten them so
        // LD_LIBRARY_PATH=$libDir alone is sufficient. Also copy them to a
        // sibling location in case the user inspects the bundle.
        $this->components->task('Flattening Termux libs', function () use ($libDir) {
            $candidateRoot = "{$libDir}/data/data/com.termux/files/usr/lib";
            if (is_dir($candidateRoot)) {
                foreach (scandir($candidateRoot) as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    if (!str_ends_with($entry, '.so') && !preg_match('/\.so\.\d+/', $entry)) continue;
                    copy("{$candidateRoot}/{$entry}", "{$libDir}/{$entry}");
                }
            }
            // Clean up the deeply nested layout we just flattened.
            $this->rrmdir("{$libDir}/data");
            return true;
        });

        return true;
    }

    protected function extractDebInto(string $debPath, string $destDir): bool
    {
        // .deb = `ar` archive containing data.tar.xz (or data.tar.gz).
        // See OpenWaInstall::extractDebInto() for the rationale — we re-implement
        // here because OpenWaBundle doesn't extend OpenWaInstall and `ar` is not
        // available on Windows hosts.
        if (!is_file($debPath)) return false;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $fp = @fopen($debPath, 'rb');
        if (!$fp) return false;

        $magic = fread($fp, 8);
        if ($magic !== "!<arch>\n") {
            fclose($fp);
            return false;
        }

        $dataTarName = null;
        $dataTarOffset = null;
        $dataTarSize = null;

        while (!feof($fp)) {
            $header = fread($fp, 60);
            if (strlen($header) < 60) break;
            $size = (int)trim(substr($header, 48, 10));
            $name = trim(substr($header, 0, 16));
            $name = rtrim($name, "/ \t");

            $dataStart = ftell($fp);

            if (in_array($name, ['data.tar.xz', 'data.tar.gz', 'data.tar.bz2', 'data.tar.lzma'], true)) {
                $dataTarName = $name;
                $dataTarOffset = $dataStart;
                $dataTarSize = $size;
                break;
            }

            $skip = $size + ($size % 2);
            fseek($fp, $dataStart + $skip, SEEK_SET);
        }

        if ($dataTarName === null) {
            fclose($fp);
            return false;
        }

        fseek($fp, $dataTarOffset, SEEK_SET);
        $tmpTar = $destDir . '/.' . basename($dataTarName) . '.tmp';
        $out = fopen($tmpTar, 'wb');
        $left = $dataTarSize;
        while ($left > 0 && !feof($fp)) {
            $buf = fread($fp, min(1 << 20, $left));
            if ($buf === false || $buf === '') break;
            fwrite($out, $buf);
            $left -= strlen($buf);
        }
        fclose($out);
        fclose($fp);

        try {
            if (str_ends_with($dataTarName, '.xz')) {
                $decompressed = substr($tmpTar, 0, -3);
                $xzCode = 255;
                if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
                    $xzPath = trim((string)shell_exec('command -v xz 2>/dev/null'));
                    if ($xzPath !== '') {
                        exec("xz -d -k -f \"{$tmpTar}\" 2>/dev/null", $xzOut, $xzCode);
                    }
                }
                if ($xzCode !== 0 && function_exists('xzdecrypt')) {
                    $data = file_get_contents($tmpTar);
                    if ($data !== false) {
                        file_put_contents($decompressed, xzdecrypt($data) ?: '');
                    }
                }
                if (!file_exists($decompressed)) {
                    @unlink($tmpTar);
                    return false;
                }
                $phar = new \PharData($decompressed);
                $phar->extractTo($destDir, overwrite: true);
                @unlink($decompressed);
            } else if (str_ends_with($dataTarName, '.gz')) {
                $decompressed = substr($tmpTar, 0, -3);
                if (!file_exists($decompressed)) {
                    $gz = gzopen($tmpTar, 'rb');
                    $out2 = fopen($decompressed, 'wb');
                    while (!gzeof($gz)) {
                        fwrite($out2, gzread($gz, 1 << 20));
                    }
                    gzclose($gz);
                    fclose($out2);
                }
                $phar = new \PharData($decompressed);
                $phar->extractTo($destDir, overwrite: true);
                @unlink($decompressed);
            } else if (str_ends_with($dataTarName, '.bz2')) {
                $decompressed = substr($tmpTar, 0, -4);
                if (!file_exists($decompressed)) {
                    $bz = bzopen($tmpTar, 'r');
                    $out2 = fopen($decompressed, 'wb');
                    while (!feof($bz)) {
                        fwrite($out2, bzread($bz, 1 << 20));
                    }
                    bzclose($bz);
                    fclose($out2);
                }
                $phar = new \PharData($decompressed);
                $phar->extractTo($destDir, overwrite: true);
                @unlink($decompressed);
            } else {
                $phar = new \PharData($tmpTar);
                $phar->extractTo($destDir, overwrite: true);
            }
        } catch (\Throwable $e) {
            @unlink($tmpTar);
            return false;
        }

        @unlink($tmpTar);
        return true;
    }

    protected function findFileRecursive(string $dir, string $filename): ?string
    {
        if (!is_dir($dir)) return null;
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }
        return null;
    }

    /**
     * Map of Termux dependency packages required by the nodejs-lts binary.
     * Mirror of OpenWaInstall::termuxDependencyUrls(). Keep in sync.
     */
    protected function termuxDependencyUrls(string $archShort): array
    {
        $packages = [
            'libc++'    => 'libc++/libc++_29',
            'openssl'   => 'openssl/openssl_1:3.6.3',
            'c-ares'    => 'c-ares/c-ares_1.34.8',
            'libicu'    => 'libicu/libicu_78.3',
            'libsqlite' => 'libsqlite/libsqlite_3.53.4',
        ];
        $base = 'https://packages.termux.dev/apt/termux-main/pool/main';
        $urls = [];
        foreach ($packages as $name => $pathSuffix) {
            $poolSub = strtolower(substr($name, 0, 1));
            $cleanName = preg_replace('/\+.*$/', '', $name);
            if (strlen($cleanName) >= 4) {
                $poolSub = strtolower(substr($cleanName, 0, 4));
            }
            $urls[$name] = "{$base}/{$poolSub}/{$name}/" . basename($pathSuffix) . "_{$archShort}.deb";
        }
        return $urls;
    }

    protected function download(string $url, string $dest): bool
    {
        $fp = fopen($dest, 'w+');
        if (!$fp) return false;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'MGS-Installer/1.0',
        ]);

        $ok = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode >= 400) {
            @unlink($dest);
            return false;
        }
        return true;
    }

    protected function generateAppEnv(string $appDir, string $apiKey): void
    {
        $config = [
            'NODE_ENV' => 'production',
            'PORT' => '2785',
            'API_KEY' => $apiKey,
            'AUTO_START_SESSIONS' => 'true',
            'SESSION_DATA_PATH' => './data/sessions',
            'LOG_LEVEL' => 'error',
        ];

        $lines = ["# MGS WhatsApp Gateway configuration\n"];
        foreach ($config as $key => $value) {
            $lines[] = "{$key}={$value}\n";
        }

        file_put_contents("{$appDir}/.env", implode('', $lines));
    }

    protected function rcopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === 'node_modules') continue;
            $srcPath = "{$src}/{$file}";
            $dstPath = "{$dst}/{$file}";
            if (is_dir($srcPath)) {
                $this->rcopy($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    protected function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;

        // On Windows, RecursiveDirectoryIterator fails on junctions/symlinks,
        // and unlink() fails when files are held open by antivirus. Shell out.
        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = str_replace('/', DIRECTORY_SEPARATOR, $dir);
            exec('rd /s /q "' . $normalized . '" 2>NUL', $o, $code);
            if (!is_dir($dir)) return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir() && !is_link($item->getPathname())) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }
        @rmdir($dir);
    }

    protected function dirSize(string $dir): int
    {
        $size = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($items as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }
        return $size;
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' ' . ($units[$i] ?? 'B');
    }
}
