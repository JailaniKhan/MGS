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
        // An EMPTY modules dir still passes is_dir() — count actual files so a
        // cleared source tree can't silently ship a broken bundle.
        if (is_dir("{$buildDir}/modules") && $this->countFiles("{$buildDir}/modules") === 0) {
            $missing[] = 'modules/ is empty (source storage/app/openwa/modules has no files)';
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

        // Bundle version marker: OpenWaManager compares the marker SHIPPED in
        // the APK (bootstrap/openwa/.bundle_version) against the on-device
        // runtime copy to decide whether the bundle must be re-deployed.
        // Without it, an APK update with a fixed server.js never replaced the
        // stale on-device gateway, because the deploy fast-path skipped once
        // server.js existed.
        //
        // The marker must therefore be written to bootstrap/openwa/ (which
        // ships inside the APK), NOT only into build/ — otherwise every build
        // ships the same marker and upgrades never redeploy. Previously the
        // marker was only generated in build/, so successive APKs carried an
        // unchanged bootstrap marker.
        $bundleVersion = gmdate('YmdHis') . '-' . substr(md5(random_bytes(16)), 0, 8);
        file_put_contents("{$buildDir}/.bundle_version", $bundleVersion);
        $shippedMarkerDir = base_path('bootstrap/openwa');
        if (!is_dir($shippedMarkerDir)) {
            mkdir($shippedMarkerDir, 0755, true);
        }
        file_put_contents("{$shippedMarkerDir}/.bundle_version", $bundleVersion);

        // CRITICAL for on-device startup: the Android runtime resolves the node
        // binary via `storage_path('app/openwa/node/node')` (the SOURCE tree,
        // not this build/ tree), and sets LD_LIBRARY_PATH to
        // `storage_path('app/openwa/node/lib')`. The source tree is created by
        // `openwa:install` with the raw, nested Termux layout
        // (`lib/data/data/com.termux/files/usr/lib/*.so`). Flatten it here so
        // the APK bundle ships a node/lib/ that LD_LIBRARY_PATH can actually
        // resolve. Without this, node fails to dlopen its .so deps on-device
        // → gateway never starts → no QR / "WhatsApp not working" in the APK.
        $sourceNodeLib = "{$runtimeDir}/node/lib";
        if (is_dir($sourceNodeLib)) {
            $this->components->task('Flattening source node/lib for APK', function () use ($sourceNodeLib) {
                $this->flattenTermuxLibs($sourceNodeLib);
                return true;
            });
        }

        // Keep the APK lean: Termux .deb extraction materializes soname
        // symlinks as duplicate copies (libicudata.so.78 + libicudata.so.78.3,
        // 31.6MB each) and `npm install` on a Windows host pulls in
        // platform-specific optional deps (sharp-win32-x64, ~18MB) plus
        // @types packages that never run on Android. Prune them from BOTH
        // the build tree and the shipped bootstrap/openwa tree.
        $this->components->task('Pruning redundant bundle files', function () use ($buildDir) {
            foreach ([
                "{$buildDir}/node/lib",
                base_path('bootstrap/openwa/node/lib'),
            ] as $libDir) {
                $this->pruneRedundantLibCopies($libDir);
            }
            foreach ([
                "{$buildDir}/modules",
                base_path('bootstrap/openwa/modules'),
            ] as $modulesDir) {
                $this->pruneNodeModules($modulesDir);
            }
            return true;
        });

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

        // Note: $this->components->task() is void (it doesn't return the
        // closure's result), so we capture success via a by-ref $ok instead.
        $ok = false;
        $this->components->task('Extracting Node.js binary', function () use ($archive, $buildDir, $dest, $libDir, &$ok) {
            $tmp = storage_path('app/openwa/downloads/_node_extract_bundle');
            if (is_dir($tmp)) $this->rrmdir($tmp);
            mkdir($tmp, 0755, true);

            $innerOk = $this->extractDebInto($archive, $tmp);
            if (!$innerOk) {
                $this->error("Deb extraction failed for {$archive}");
                $ok = false;
                return;
            }

            // Locate `node` binary inside the extracted Termux tree.
            $nodeBin = $this->findFileRecursive($tmp, 'node');
            if (!$nodeBin) {
                $this->error('node binary not found inside extracted .deb');
                $ok = false;
                return;
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

            $ok = true;
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
                // Sanitize: Termux encodes "epoch:version" as "1:3.6.3", but
                // ':' is illegal in Windows filenames. Replace with '-'.
                $debName = str_replace(':', '-', $debName);
                $debPath = storage_path("app/openwa/downloads/termux-{$debName}");

                if (!is_file($debPath) || filesize($debPath) < 1000) {
                    if (is_file($debPath)) {
                        @unlink($debPath);
                    }
                    $downloaded = $this->download($debUrl, $debPath);
                    if (!$downloaded || !is_file($debPath) || filesize($debPath) < 1000) {
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
        // LD_LIBRARY_PATH=$libDir alone is sufficient.
        $this->flattenTermuxLibs($libDir);

        // The Termux .deb packages ship soname symlinks (libz.so.1 ->
        // libz.so.1.3.2, libsqlite3.so -> libsqlite3.so.3.53.4, ...). Windows
        // tar / PharData rarely preserves symlinks, so those alias names are
        // missing after extraction. The on-device Android linker resolves
        // DT_NEEDED by the EXACT filename (e.g. "libicuuc.so.78"), so every
        // short name must exist as a real file. Materialize them as copies.
        $this->materializeSonameAliases($libDir);

        return true;
    }

    /**
     * For every versioned shared library (matching `libX.so.[0-9]+...`),
     * create real copies for the soname forms the on-device linker requests:
     *   libicui18n.so.78.3   -> libicui18n.so.78   (major-only soname)
     *   libicudata.so.78.3   -> libicudata.so.78   (transitive dep)
     *   libz.so.1.3.2        -> libz.so.1
     *   libsqlite3.so.3.53.4 -> libsqlite3.so.3, libsqlite3.so (node links
     *                           the unversioned name)
     * We deliberately do NOT create a blanket unversioned alias for every
     * library (that would duplicate ~60MB of ICU data files); only sqlite's
     * unversioned name is required by the node binary.
     */
    protected function materializeSonameAliases(string $libDir): void
    {
        if (!is_dir($libDir)) {
            return;
        }
        foreach (scandir($libDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = "{$libDir}/{$entry}";
            if (!is_file($path)) continue;
            // lib<name>.so.<major>.<minor>.<patch? ...>
            if (!preg_match('/^(.+\.so)\.([0-9]+)(\.[0-9]+)*$/', $entry, $m)) {
                continue;
            }
            $base = $m[1];
            $major = $m[2];
            $aliases = ["{$base}.{$major}"];
            if ($base === 'libsqlite3.so') {
                $aliases[] = $base;
            }
            foreach ($aliases as $alias) {
                if ($alias === $entry || file_exists("{$libDir}/{$alias}")) {
                    continue;
                }
                @copy($path, "{$libDir}/{$alias}");
            }
        }
    }

    /**
     * Remove redundant shared-library copies that bloat the APK bundle.
     *
     * The Termux .deb packages ship soname symlinks (libicudata.so.78 ->
     * libicudata.so.78.3). Windows tar/PharData extraction materializes those
     * symlinks as full duplicate files, and materializeSonameAliases() then
     * copies the fully-versioned name to the major-only name the Android
     * linker requests. After that step the fully-versioned and malformed
     * variants are pure dead weight — the linker only ever opens the exact
     * DT_NEEDED filename (e.g. "libicudata.so.78", "libsqlite3.so.3").
     */
    protected function pruneRedundantLibCopies(string $libDir): void
    {
        if (!is_dir($libDir)) {
            return;
        }
        foreach (scandir($libDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = "{$libDir}/{$entry}";
            if (!is_file($path)) continue;
            // Fully-versioned copies: libicudata.so.78.3, libz.so.1.3.2,
            // libsqlite3.so.3.53.4 — the major-only alias is kept.
            if (preg_match('/^.+\.so\.[0-9]+(\.[0-9]+)+$/', $entry)) {
                @unlink($path);
                continue;
            }
            // Malformed variants from broken extraction: libsqlite3.53.4.so.
            if (preg_match('/^.+\.\d+\.\d+\.so$/', $entry)) {
                @unlink($path);
            }
        }
    }

    /**
     * Remove npm packages that are dead weight in an Android APK:
     *   - @img/* (sharp + platform bindings): optional baileys image
     *     dependency; on a Windows host npm installs sharp-win32-x64, a
     *     Windows DLL that can never load on Android. Without a linux-arm64
     *     binding sharp fails at runtime, so nothing is lost by pruning it.
     *   - @types/*: TypeScript definitions, never used at runtime.
     *   - sharp (main package): unusable without its platform binding.
     */
    protected function pruneNodeModules(string $modulesDir): void
    {
        foreach (['@img', '@types', 'sharp'] as $name) {
            $this->rrmdir("{$modulesDir}/{$name}");
        }
    }

    /**
     * Copy the .so files from the nested Termux layout
     * `<libDir>/data/data/com.termux/files/usr/lib/*` directly into
     * `<libDir>/`, then remove the deeply-nested `data/` subtree so only the
     * flat libs remain. This MUST be applied to BOTH the build's `node/lib`
     * and the source `storage/app/openwa/node/lib`, because OpenWaManager on
     * Android resolves the node binary via `storage_path('app/openwa/node/node')
     * (the source tree), NOT `build/node/node` — and its buildEnvVars() sets
     * `LD_LIBRARY_PATH=<thatDir>/lib`. If the source lib/ still has the nested
     * Termux layout, the dynamic linker finds no .so there and node fails to
     * start on-device (gateway dead, no QR).
     */
    protected function flattenTermuxLibs(string $libDir): void
    {
        if (!is_dir($libDir)) {
            return;
        }
        $candidateRoot = "{$libDir}/data/data/com.termux/files/usr/lib";
        if (is_dir($candidateRoot)) {
            foreach (scandir($candidateRoot) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                if (!str_ends_with($entry, '.so') && !preg_match('/\.so\.\d+/', $entry)) continue;
                copy("{$candidateRoot}/{$entry}", "{$libDir}/{$entry}");
            }
        }
        // Remove the deeply nested layout we just flattened. Also drop any
        // other Termux prefix junk (bin, share, include, man, pkgconfig
        // mirrors) that came along for the ride — none of it is needed at
        // runtime, only the flat .so files are.
        foreach (['data', 'bin', 'share', 'include', 'etc', 'var'] as $junk) {
            $this->rrmdir("{$libDir}/{$junk}");
        }
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
                // $tmpTar has the form ".../.data.tar.xz.tmp"; produce a .tar
                // filename so downstream PharData / tar-binary extraction
                // recognises the format.
                $decompressed = preg_replace('/\.xz\.tmp$/', '.tar', $tmpTar);
                if (!file_exists($decompressed)) {
                    $xzCode = $this->ensureXzDecompress($tmpTar, $decompressed);
                    if ($xzCode !== 0 && !is_file($decompressed)) {
                        @unlink($tmpTar);
                        @unlink($decompressed);
                        return false;
                    }
                }
                if (!file_exists($decompressed)) {
                    @unlink($tmpTar);
                    return false;
                }
                if (!$this->safeTarExtract($decompressed, $destDir)) {
                    @unlink($decompressed);
                    @unlink($tmpTar);
                    return false;
                }
                @unlink($decompressed);
            } else if (str_ends_with($dataTarName, '.gz')) {
                $decompressed = preg_replace('/\.gz\.tmp$/', '.tar', $tmpTar);
                if (!file_exists($decompressed)) {
                    $gz = gzopen($tmpTar, 'rb');
                    $out2 = fopen($decompressed, 'wb');
                    while (!gzeof($gz)) {
                        fwrite($out2, gzread($gz, 1 << 20));
                    }
                    gzclose($gz);
                    fclose($out2);
                }
                if (!$this->safeTarExtract($decompressed, $destDir)) {
                    @unlink($decompressed);
                    @unlink($tmpTar);
                    return false;
                }
                @unlink($decompressed);
            } else if (str_ends_with($dataTarName, '.bz2')) {
                $decompressed = preg_replace('/\.bz2\.tmp$/', '.tar', $tmpTar);
                if (!file_exists($decompressed)) {
                    $bz = bzopen($tmpTar, 'r');
                    $out2 = fopen($decompressed, 'wb');
                    while (!feof($bz)) {
                        fwrite($out2, bzread($bz, 1 << 20));
                    }
                    bzclose($bz);
                    fclose($out2);
                }
                if (!$this->safeTarExtract($decompressed, $destDir)) {
                    @unlink($decompressed);
                    @unlink($tmpTar);
                    return false;
                }
                @unlink($decompressed);
            } else {
                if (!$this->safeTarExtract($tmpTar, $destDir)) {
                    @unlink($tmpTar);
                    return false;
                }
            }
        } catch (\Throwable $e) {
            @unlink($tmpTar);
            return false;
        }

        @unlink($tmpTar);
        return true;
    }

    /**
     * Safely extract a .tar file. Mirror of OpenWaInstall::safeTarExtract().
     * On Windows, PharData::extractTo() fails on Termux tars that contain
     * `.` and `..` entries. Try the `tar` binary first.
     */
    protected function safeTarExtract(string $tarPath, string $destDir): bool
    {
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        if (!is_file($tarPath)) {
            return false;
        }

        $tarBin = null;
        if (PHP_OS_FAMILY === 'Windows') {
            if (@is_file('C:\Windows\System32\tar.exe')) {
                $tarBin = 'C:\Windows\System32\tar.exe';
            } else {
                exec('where tar 2>NUL', $out, $code);
                if ($code === 0 && !empty($out[0])) {
                    $tarBin = trim($out[0]);
                }
            }
        } else {
            exec('command -v tar 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $tarBin = trim($out[0]);
            }
        }

        if ($tarBin !== null) {
            $cmd = '"' . $tarBin . '" -xf "' . $tarPath . '" -C "' . $destDir . '"';
            $cmd .= (PHP_OS_FAMILY === 'Windows') ? ' 2>NUL' : ' 2>/dev/null';
            exec($cmd, $out, $code);
            if ($this->dirHasAnyFile($destDir)) return true;
        }

        try {
            $phar = new \PharData($tarPath);
            $phar->extractTo($destDir, overwrite: true);
            if ($this->dirHasAnyFile($destDir)) return true;
        } catch (\Throwable $e) {
        }

        try {
            $phar = new \PharData($tarPath);
            foreach ($phar as $key => $file) {
                if ($key === '.' || $key === '..') continue;
                $relative = ltrim((string)$key, './');
                if ($relative === '' || str_starts_with($relative, '..')) continue;
                $target = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
                if ($file->isDir()) {
                    @mkdir($target, 0755, true);
                } else {
                    @mkdir(dirname($target), 0755, true);
                    copy('phar://' . $tarPath . '/' . ltrim((string)$key, '/'), $target);
                }
            }
            return $this->dirHasAnyFile($destDir);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function dirHasAnyFile(string $dir): bool
    {
        if (!is_dir($dir)) return false;
        try {
            $rii = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($rii as $f) {
                if ($f->isFile()) return true;
            }
        } catch (\Throwable $e) {
        }
        return false;
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
     * Decompress a .xz file to a target .tar path using the most reliable
     * strategy available on this host. Returns 0 on success (xz convention).
     * Mirror of OpenWaInstall::ensureXzDecompress().
     */
    protected function ensureXzDecompress(string $xzPath, string $outPath): int
    {
        if (is_file($outPath) && filesize($outPath) > 0) {
            return 0;
        }

        // Strategy 1: PATH-resident xz (redirect form, never in-place).
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where xz 2>NUL', $xzWhere, $xzWhereCode);
            if ($xzWhereCode === 0 && !empty($xzWhere[0])) {
                $xzBin = trim($xzWhere[0]);
                $code = $this->runXzTo($xzBin, $xzPath, $outPath);
                if ($code === 0 && is_file($outPath) && filesize($outPath) > 0) {
                    return 0;
                }
            }
        } else {
            $xzBin = trim((string)@shell_exec('command -v xz 2>/dev/null'));
            if ($xzBin !== '') {
                $code = $this->runXzTo($xzBin, $xzPath, $outPath);
                if ($code === 0 && is_file($outPath) && filesize($outPath) > 0) {
                    return 0;
                }
            }
        }

        // Strategy 2: bundled xz.exe (Git for Windows, MSYS2, Cygwin).
        $code = $this->tryBundledXz($xzPath, $outPath);
        if ($code === 0 && is_file($outPath) && filesize($outPath) > 0) {
            return 0;
        }

        // Strategy 3: PECL lzma extension.
        if (function_exists('xzdecrypt')) {
            $data = file_get_contents($xzPath);
            $dec = $data !== false ? xzdecrypt($data) : false;
            if ($dec !== false && $dec !== '') {
                file_put_contents($outPath, $dec);
                return 0;
            }
        }

        // Strategy 4: Python stdlib lzma — throws if Python missing; caller catches.
        $this->xzDecompressToFile($xzPath, $outPath);
        return (is_file($outPath) && filesize($outPath) > 0) ? 0 : 1;
    }

    /**
     * Run `xz -d -k -f -c <in>` and capture stdout into $outPath.
     * Mirror of OpenWaInstall::runXzTo().
     */
    protected function runXzTo(string $xzBin, string $inPath, string $outPath): int
    {
        $cmd = '"' . $xzBin . '" -d -k -f -c "' . $inPath . '"';
        $descriptors = [
            0 => ['file', 'nul', 'r'],
            1 => ['file', str_replace('/', DIRECTORY_SEPARATOR, $outPath), 'wb'],
            2 => ['file', 'nul', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return 1;
        }
        proc_close($proc);
        return is_file($outPath) && filesize($outPath) > 0 ? 0 : 1;
    }

    /**
     * Look for a bundled `xz` executable in well-known locations on Windows
     * (Git for Windows, MSYS2, Cygwin) and use it to decompress the file.
     * Returns 0 on success. Mirror of OpenWaInstall::tryBundledXz().
     */
    protected function tryBundledXz(string $xzPath, string $outPath): int
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return 1;
        }
        $candidates = [
            'C:\Program Files\Git\mingw64\bin\xz.exe',
            'C:\Program Files\Git\usr\bin\xz.exe',
            'C:\Program Files (x86)\Git\mingw64\bin\xz.exe',
            'C:\Program Files (x86)\Git\usr\bin\xz.exe',
            'C:\msys64\usr\bin\xz.exe',
            'C:\cygwin64\bin\xz.exe',
            'C:\cygwin\bin\xz.exe',
        ];
        foreach ($candidates as $candidate) {
            if (@is_file($candidate)) {
                $cmd = '"' . $candidate . '" -d -k -f -c "' . $xzPath . '"';
                $descriptors = [
                    0 => ['file', 'nul', 'r'],
                    1 => ['file', $outPath, 'wb'],
                    2 => ['file', 'nul', 'w'],
                ];
                $proc = @proc_open($cmd, $descriptors, $pipes);
                if (is_resource($proc)) {
                    proc_close($proc);
                    if (is_file($outPath) && filesize($outPath) > 0) {
                        return 0;
                    }
                }
            }
        }
        return 1;
    }

    /**
     * Pure-PHP XZ decompressor fallback. Mirror of OpenWaInstall::xzDecompressToFile().
     * Tries (1) ext-lzma `xzdecrypt()`, (2) Python's stdlib `lzma` module,
     * (3) throws with a helpful message.
     */
    protected function xzDecompressToFile(string $xzPath, string $tarPath): void
    {
        if (function_exists('xzdecrypt')) {
            $data = file_get_contents($xzPath);
            $decompressed = $data !== false ? xzdecrypt($data) : false;
            if ($decompressed !== false && $decompressed !== '') {
                file_put_contents($tarPath, $decompressed);
                return;
            }
        }

        $python = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
        $pyScript = <<<PY
import lzma, sys
with open(sys.argv[1], 'rb') as f_in:
    with lzma.LZMAFile(f_in) as xz:
        with open(sys.argv[2], 'wb') as f_out:
            while True:
                chunk = xz.read(1 << 20)
                if not chunk:
                    break
                f_out.write(chunk)
PY;
        $tmpPy = sys_get_temp_dir() . '/mgs_bundle_xz_' . bin2hex(random_bytes(4)) . '.py';
        file_put_contents($tmpPy, $pyScript);
        $cmd = escapeshellarg($python) . ' ' . escapeshellarg($tmpPy) . ' ' . escapeshellarg($xzPath) . ' ' . escapeshellarg($tarPath);
        $descriptors = [
            0 => ['file', 'nul', 'r'],
            1 => ['file', 'nul', 'w'],
            2 => ['file', 'nul', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            proc_close($proc);
            @unlink($tmpPy);
            if (is_file($tarPath) && filesize($tarPath) > 0) {
                return;
            }
        }
        @unlink($tmpPy);

        throw new \RuntimeException(
            'xz decompression failed: could not find `xz` on PATH, in Git for ' .
            'Windows, or via the Python `lzma` module. To fix this on Windows, ' .
            'install Git for Windows (https://git-scm.com/win) which ships ' .
            'xz.exe, or install Python (https://python.org) which provides ' .
            'the `lzma` stdlib module. Then re-run the command.'
        );
    }

    /**
     * Map of Termux dependency packages required by the nodejs-lts binary.
     * Mirror of OpenWaInstall::termuxDependencyUrls(). Keep in sync.
     */
    protected function termuxDependencyUrls(string $archShort): array
    {
        // Pinned versions confirmed in the Termux apt repo (2026-07).
        // zlib MUST be included: the Termux node binary links against
        // "libz.so.1", but stock Android only ships libz.so (wrong soname),
        // so the on-device linker fails with "library libz.so.1 not found".
        $packages = [
            'libc++'    => ['libc++_29',          'libc++'],
            'openssl'   => ['openssl_1:3.6.3',    'openssl'],
            'c-ares'    => ['c-ares_1.34.8',      'c-ares'],
            'libicu'    => ['libicu_78.3',        'libicu'],
            'libsqlite' => ['libsqlite_3.53.4',  'libsqlite'],
            'zlib'      => ['zlib_1.3.2',         'zlib'],
        ];
        $base = 'https://packages.termux.dev/apt/termux-main/pool/main';
        $urls = [];
        foreach ($packages as $name => [$pathSuffix, $dirName]) {
            // Termux Debian-style pool layout:
            //   - `lib*` packages use a 4-char prefix subdir (libs, libc, etc.)
            //   - other packages use a 1-char prefix subdir (o, c, n, ...)
            if (str_starts_with($name, 'lib')) {
                $poolSub = strtolower(substr($name, 0, 4));
            } else {
                $poolSub = strtolower(substr($name, 0, 1));
            }
            $urls[$name] = "{$base}/{$poolSub}/{$dirName}/{$pathSuffix}_{$archShort}.deb";
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
        // Skip dev-machine runtime state that must not ship in the APK:
        // node_modules (copied separately as modules/), the linked session
        // creds under data/, and diagnostic logs.
        $skip = ['node_modules', 'data', 'baileys.log', 'openwa_app.log', 'openwa_app_err.log', 'downloads'];
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || in_array($file, $skip, true)) continue;
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

    protected function countFiles(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($items as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }
        return $count;
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
