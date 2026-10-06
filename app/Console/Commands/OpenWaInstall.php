<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OpenWaInstall extends Command
{
    protected $signature = 'openwa:install
        {--target= : Target platform: android-arm64, linux-x64, win-x64 (auto-detect by default)}
        {--from= : Path to an existing OpenWA installation (e.g. D:\\Mobile App\\OpenWA)}
        {--lightweight : Install a lightweight WhatsApp gateway (~48MB) instead of full OpenWA}
        {--openwa-version= : OpenWA release tag (default: latest)}
        {--node-version= : Node.js version (default: 22.14.0)}
        {--no-deps : Skip npm install step}
        {--no-build : Skip npm run build (use when dist/ already exists)}
        {--no-node : Skip Node.js download step}
        {--force : Overwrite existing installation}';

    protected $description = 'Download and install Node.js + OpenWA for the target platform';

    protected string $runtimeDir;

    public function handle(): int
    {
        $this->runtimeDir = storage_path('app/openwa');

        if (is_dir($this->runtimeDir) && ! $this->option('force')) {
            $this->components->twoColumnDetail('Installation exists', $this->runtimeDir);
            $this->warn('Use --force to overwrite, or skip if already set up.');

            return self::SUCCESS;
        }

        $target = $this->option('target') ?: $this->detectTarget();
        $nodeVersion = $this->option('node-version') ?: $this->defaultNodeVersion($target);

        $this->components->twoColumnDetail('Target platform', $target);
        $this->components->twoColumnDetail('Node.js version', $nodeVersion);

        $this->ensureDirectories();

        if ($this->option('force') && is_dir("{$this->runtimeDir}/modules")) {
            $this->components->task('Cleaning previous npm modules', fn () => $this->rrmdir("{$this->runtimeDir}/modules") ?: true);
            mkdir("{$this->runtimeDir}/modules", 0755, true);
        }

        $nodeBinary = null;
        if (! $this->option('no-node')) {
            $nodeBinary = $this->installNode($target, $nodeVersion);
            if (! $nodeBinary) {
                return self::FAILURE;
            }
        }

        $apiKey = env('OPENWA_API_KEY') ?: $this->generateApiKey();

        // On Android we MUST use the lightweight Baileys-only variant — the
        // full OpenWA bundle depends on puppeteer/Chromium, dockerode, postgres,
        // redis and other services that cannot run on stock Android. Baileys is
        // a pure-JS WhatsApp Web implementation and works fine.
        $forceLightweight = str_starts_with($target, 'android-');

        if ($this->option('lightweight') || $forceLightweight) {
            $appDir = $this->installLightweight();
            if (! $appDir) {
                return self::FAILURE;
            }
        } else {
            $appDir = $this->installOpenwa();
            if (! $appDir) {
                return self::FAILURE;
            }

            if (! $this->option('no-deps')) {
                $this->installDependencies($appDir);
            }

            if (! $this->option('no-build') && ! $this->option('lightweight')) {
                $this->buildOpenwa($appDir);
            }
        }

        if ($nodeBinary) {
            $this->writeEnvConfig($appDir, $nodeBinary, $apiKey);
        }
        $this->writeOpenwaEnv($appDir, $apiKey);

        $this->newLine();
        $this->components->success('OpenWA installation complete!');
        $this->line("  API Key: <fg=green>{$apiKey}</>");
        $this->line("  Node:    {$nodeBinary}");
        $this->line("  App:     {$appDir}");
        $this->newLine();
        $this->warn('Run "php artisan openwa:bundle" before building the APK to verify everything is ready.');

        return self::SUCCESS;
    }

    protected function writeOpenwaEnv(string $appDir, string $apiKey): void
    {
        $envPath = "{$appDir}/.env";

        $config = [
            'NODE_ENV' => 'production',
            'PORT' => '2785',
            'API_KEY' => $apiKey,
            'AUTH_TYPE' => 'api-key',
            'AUTO_START_SESSIONS' => 'true',
            'MAX_CONCURRENT_SESSIONS' => '1',
            'SESSION_DATA_PATH' => './data/sessions',
            'ENGINE_TYPE' => 'baileys',
            'DATABASE_TYPE' => 'sqlite',
            'DATABASE_PATH' => './data/database.sqlite',
            'DATABASE_SYNCHRONIZE' => 'true',
            'DATABASE_LOGGING' => 'false',
            'STORAGE_TYPE' => 'local',
            'STORAGE_LOCAL_PATH' => './data/media',
            'DASHBOARD_ENABLED' => 'false',
            'REDIS_ENABLED' => 'false',
            'REDIS_BUILTIN' => 'false',
            'QUEUE_ENABLED' => 'false',
            'CACHE_ENABLED' => 'false',
            'POSTGRES_BUILTIN' => 'false',
            'MINIO_BUILTIN' => 'false',
            'LOG_LEVEL' => 'error',
            'LOG_FORMAT' => 'json',
            'DOMAIN' => 'localhost',
            'CORS_ORIGINS' => '*',
            'PUPPETEER_HEADLESS' => 'true',
            'PUPPETEER_ARGS' => '--no-sandbox,--disable-setuid-sandbox,--disable-dev-shm-usage',
        ];

        $lines = ["# OpenWA configuration for MGS bundled gateway\n"];
        foreach ($config as $key => $value) {
            $lines[] = "{$key}={$value}\n";
        }

        file_put_contents($envPath, implode('', $lines));
        $this->components->twoColumnDetail('OpenWA .env', 'generated');
    }

    protected function detectTarget(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'win-x64';
        }

        $machine = php_uname('m');
        if (PHP_OS_FAMILY === 'Darwin') {
            return str_contains($machine, 'arm') ? 'darwin-arm64' : 'darwin-x64';
        }

        return str_contains($machine, 'aarch64') ? 'linux-arm64' : 'linux-x64';
    }

    protected function ensureDirectories(): void
    {
        $dirs = [
            $this->runtimeDir,
            "{$this->runtimeDir}/downloads",
            "{$this->runtimeDir}/node",
            "{$this->runtimeDir}/app",
            "{$this->runtimeDir}/modules",
        ];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    protected function installLightweight(): ?string
    {
        $appDir = "{$this->runtimeDir}/app";

        if (is_file("{$appDir}/server.js") && ! $this->option('force')) {
            $this->components->twoColumnDetail('Lightweight gateway', 'already installed');

            return $appDir;
        }

        $this->components->task('Creating lightweight gateway', function () use ($appDir) {
            if (is_dir($appDir)) {
                $this->rrmdir($appDir);
            }
            mkdir($appDir, 0755, true);

            $packageJson = [
                'name' => 'mgs-wa-gateway',
                'version' => '1.0.0',
                'private' => true,
                'dependencies' => [
                    '@whiskeysockets/baileys' => '^6.7.0',
                    '@hapi/boom' => '^10.0.0',
                    'dotenv' => '^16.4.0',
                    'express' => '^4.21.0',
                    'qrcode' => '^1.5.4',
                ],
            ];

            file_put_contents(
                "{$appDir}/package.json",
                json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $serverCode = 'require("dotenv").config();
const express = require("express");
const { makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require("@whiskeysockets/baileys");
const { Boom } = require("@hapi/boom");
const http = require("http");
const fs = require("fs");
const path = require("path");
const QR = require("qrcode");

const PORT = parseInt(process.env.PORT || "2785", 10);
const API_KEY = process.env.API_KEY || "";
const SESSION_DATA_PATH = process.env.SESSION_DATA_PATH || "./data/sessions";
const AUTO_START = process.env.AUTO_START_SESSIONS !== "false";
const sessions = new Map();
// Diagnostic logger: baileys calls logger.child() internally, so the object
// must support that. We forward warn/error (and the connection events we
// log explicitly below) to openwa_app.log so QR/link failures are visible.
const _logStream = fs.createWriteStream(path.join(__dirname, "baileys.log"), { flags: "a" });
function _fmt(args) {
    return args.map(a => {
        try { return typeof a === "string" ? a : JSON.stringify(a); } catch (e) { return String(a); }
    }).join(" ");
}
function _ts() { return new Date().toISOString(); }
const logger = {
    level: "warn",
    child: function () { return logger; },
    debug: () => {},
    info: (...a) => { _logStream.write(`${_ts()} [info] ${_fmt(a)}\n`); },
    warn: (...a) => { _logStream.write(`${_ts()} [warn] ${_fmt(a)}\n`); },
    error: (...a) => { _logStream.write(`${_ts()} [error] ${_fmt(a)}\n`); },
    trace: () => {},
    fatal: (...a) => { _logStream.write(`${_ts()} [fatal] ${_fmt(a)}\n`); },
};
function logEvent(id, msg, extra) {
    _logStream.write(`${_ts()} [session:${id}] ${msg}${extra ? " " + JSON.stringify(extra) : ""}\n`);
}

async function getSession(id) {
    if (sessions.has(id)) { const s = sessions.get(id); if (s.status !== "dead") return s; }
    const s = { id, sock: null, status: "initializing", qr: null, phone: null }; sessions.set(id, s); return s;
}

async function startSession(id) {
    const s = await getSession(id);
    if (s.sock) { try { s.sock.end(undefined); } catch (e) {} }
    const authDir = path.join(__dirname, SESSION_DATA_PATH, id);
    fs.mkdirSync(authDir, { recursive: true });
    // After a logout (401) the stored creds are rejected by WA forever —
    // wipe them so the next pairing starts from a clean slate.
    if (s.status === "dead") {
        try { fs.rmSync(authDir, { recursive: true, force: true }); } catch (e) {}
        fs.mkdirSync(authDir, { recursive: true });
    }
    const { state, saveCreds } = await useMultiFileAuthState(authDir);
    const { version } = await fetchLatestBaileysVersion();
    s.status = "initializing"; s.qr = null;
    const sock = makeWASocket({ version, auth: state, printQRInTerminal: false, generateHighQualityLink: true, logger, browser: ["MGS Gateway", "Chrome", "22.14.0"] });
    s.sock = sock;
    sock.ev.on("creds.update", saveCreds);
    sock.ev.on("connection.update", (upd) => {
        if (upd.qr) { QR.toDataURL(upd.qr).then(d => { s.qr = d; }).catch(() => {}); s.status = "qr_ready"; logEvent(id, "QR generated (first valid ~60s, then rotates every 20s)"); }
        if (upd.connection === "open") { s.status = "ready"; s.qr = null; if (sock.user) s.phone = sock.user.id ? sock.user.id.split(":")[0] : null; logEvent(id, "CONNECTED", { phone: s.phone }); }
        if (upd.connection === "close") {
            const r = upd.lastDisconnect?.error instanceof Boom ? upd.lastDisconnect.error.output.statusCode : DisconnectReason.restartRequired;
            s.status = r === DisconnectReason.loggedOut ? "dead" : "disconnected";
            s.qr = null;
            logEvent(id, "connection closed", { reason: r, message: upd.lastDisconnect?.error?.message || null });
            if (r === DisconnectReason.loggedOut) {
                // The stored creds were rejected by WA (logout) and are
                // useless forever — wipe them so the next start re-pairs.
                try { fs.rmSync(authDir, { recursive: true, force: true }); } catch (e) {}
            }
            // Reconnect ONLY when the session is already linked (creds saved):
            // the post-pairing "restart required" close and ordinary drops
            // need a fresh connection. While still pairing (no creds yet) we
            // must NOT restart — a new socket would kill the live QR ~3s after
            // it appears and every scan fails with "could not link device".
            const credsPath = path.join(authDir, "creds.json");
            if (AUTO_START && r !== DisconnectReason.loggedOut && fs.existsSync(credsPath)) {
                setTimeout(() => startSession(id), 3000);
            }
        }
    });
    // Inbound messages. Baileys only hands received messages to listeners of
    // "messages.upsert" — without this subscription the gateway drops every
    // incoming message and the chat pages can never show a reply.
    // Kept in a bounded in-memory buffer per session; the PHP side polls
    // GET /api/sessions/:id/messages?since=<seq> for everything newer.
    sock.ev.on("messages.upsert", (up) => {
        try {
            for (const msg of up.messages || []) {
                if (!msg || !msg.key || msg.key.fromMe) continue;
                const chatId = String(msg.key.remoteJid || "");
                if (!chatId.endsWith("@c.us") && !chatId.endsWith("@lid")) continue;
                const m = msg.message || {};
                const text = m.conversation
                    || m.extendedTextMessage?.text
                    || m.imageMessage?.caption
                    || m.videoMessage?.caption
                    || "";
                const mediaType = m.imageMessage ? "image"
                    : m.audioMessage ? "audio"
                    : m.documentMessage ? "document"
                    : null;
                if (!text && !mediaType) continue;
                s.inboundSeq = (s.inboundSeq || 0) + 1;
                s.inbound = s.inbound || [];
                s.inbound.push({
                    seq: s.inboundSeq,
                    id: msg.key.id || null,
                    chatId,
                    text: typeof text === "string" ? text : "",
                    mediaType,
                    timestamp: Number(msg.messageTimestamp) * 1000 || Date.now(),
                });
                if (s.inbound.length > 2000) s.inbound.splice(0, s.inbound.length - 2000);
                logEvent(id, "inbound message", { seq: s.inboundSeq, chatId, len: String(text).length, mediaType });
            }
        } catch (e) {
            logEvent(id, "inbound upsert failed", { error: e.message });
        }
    });
}

const app = express(); app.use(express.json());
app.get("/api/health", (r, s) => s.json({ status: "ok", uptime: process.uptime() }));
app.use((req, res, next) => { if (!API_KEY) return next(); if (req.headers["x-api-key"] !== API_KEY && req.query.apiKey !== API_KEY) return res.status(401).json({ error: "unauthorized" }); next(); });
app.get("/api/sessions", (r, s) => { const o = {}; for (const [i, x] of sessions) o[i] = { status: x.status, phone: x.phone }; s.json(o); });
app.get("/api/sessions/:id", (r, s) => { const x = sessions.get(r.params.id); s.json(x ? { status: x.status, phone: x.phone } : { status: "not_found" }); });
app.post("/api/sessions/:id/start", async (r, s) => { try { await startSession(r.params.id); s.json({ status: "started" }); } catch (e) { s.status(500).json({ error: e.message }); } });
app.get("/api/sessions/:id/qr", (r, s) => { const x = sessions.get(r.params.id); s.json({ qrCode: x?.qr || null, status: x?.status || "not_found" }); });
// Poll inbound messages received since the given sequence number (drained by
// whatsapp:sync-inbound into the reminders table).
app.get("/api/sessions/:id/messages", (r, s) => {
    const x = sessions.get(r.params.id);
    if (!x) return s.status(404).json({ error: "unknown session" });
    const since = Number(r.query.since || 0);
    const all = x.inbound || [];
    s.json({ messages: all.filter(m => m.seq > since), cursor: all.length ? all[all.length - 1].seq : since });
});
app.post("/api/sessions/:id/pairing-code", async (r, s) => {
    if (!r.body.phoneNumber) return s.status(400).json({ error: "phoneNumber required" });
    try { const x = await getSession(r.params.id); if (!x.sock || x.status === "dead") { await startSession(r.params.id); await new Promise(r2 => setTimeout(r2, 3000)); }
        const code = await x.sock.requestPairingCode(r.body.phoneNumber); s.json({ pairingCode: code }); } catch (e) { s.status(500).json({ error: e.message }); }
});
app.post("/api/sessions/:id/messages/send-text", async (r, s) => {
    if (!r.body.chatId || !r.body.text) return s.status(400).json({ error: "chatId and text required" });
    const x = sessions.get(r.params.id); if (!x || !x.sock || x.status !== "ready") return s.status(400).json({ error: "session not ready" });
    try { const sent = await x.sock.sendMessage(r.body.chatId, { text: r.body.text }); s.json({ status: "sent", id: sent?.key?.id }); } catch (e) { s.status(500).json({ error: e.message }); }
});
app.post("/api/sessions/:id/messages/send-image", async (r, s) => {
    if (!r.body.chatId || !r.body.image) return s.status(400).json({ error: "chatId and image required" });
    const x = sessions.get(r.params.id); if (!x || !x.sock || x.status !== "ready") return s.status(400).json({ error: "session not ready" });
    try { const sent = await x.sock.sendMessage(r.body.chatId, { image: { url: r.body.image }, caption: r.body.caption || "" }); s.json({ status: "sent", id: sent?.key?.id }); } catch (e) { s.status(500).json({ error: e.message }); }
});
app.post("/api/sessions/:id/messages/send-document", async (r, s) => {
    if (!r.body.chatId || !r.body.document) return s.status(400).json({ error: "chatId and document required" });
    const x = sessions.get(r.params.id); if (!x || !x.sock || x.status !== "ready") return s.status(400).json({ error: "session not ready" });
    try { const sent = await x.sock.sendMessage(r.body.chatId, { document: { url: r.body.document }, mimetype: r.body.mimetype || "application/octet-stream", fileName: r.body.fileName || "document" }); s.json({ status: "sent", id: sent?.key?.id }); } catch (e) { s.status(500).json({ error: e.message }); }
});
http.createServer(app).listen(PORT, "127.0.0.1", () => console.log("MGS WA Gateway on http://127.0.0.1:" + PORT));
';

            // bootstrap/openwa/app/server.js is the AUTHORITATIVE gateway code
            // (the dev-machine runtime and the APK bundle read the same file).
            // Prefer it over the embedded fallback above so a fresh install is
            // always identical to the code that was actually tested — the
            // embedded string is only used when the bootstrap copy is missing.
            $bootstrapServer = base_path('bootstrap/openwa/app/server.js');
            if (is_file($bootstrapServer)) {
                $serverCode = file_get_contents($bootstrapServer);
            }

            file_put_contents("{$appDir}/server.js", $serverCode);

            return true;
        });

        if (! $this->option('no-deps')) {
            $modulesDir = "{$this->runtimeDir}/modules";
            if (is_dir("{$modulesDir}/node_modules") && count(scandir("{$modulesDir}/node_modules")) > 2) {
                $this->components->twoColumnDetail('npm dependencies', 'already installed');
            } else {
                $this->components->task('Installing npm dependencies', function () use ($appDir, $modulesDir) {
                    if (PHP_OS_FAMILY === 'Windows') {
                        $cmd = sprintf('cd /D "%s" && npm install --omit=dev 2>&1', $appDir);
                    } else {
                        $cmd = sprintf('cd "%s" && npm install --omit=dev 2>&1', $appDir);
                    }
                    exec($cmd, $output, $exitCode);
                    if ($exitCode !== 0) {
                        Log::warning('npm install failed', ['output' => implode("\n", $output)]);

                        return false;
                    }
                    $nm = "{$appDir}/node_modules";
                    if (is_dir($nm)) {
                        if (is_dir($modulesDir)) {
                            $this->rrmdir($modulesDir);
                        }
                        rename($nm, $modulesDir);
                        $stub = "{$modulesDir}/node_modules";
                        if (is_dir($stub)) {
                            $this->rrmdir($stub);
                        }
                    }

                    return true;
                });
            }
        }

        if (! is_file("{$appDir}/server.js")) {
            $this->error('Failed to create lightweight gateway');

            return null;
        }

        return $appDir;
    }

    protected function installNode(string $target, string $version): ?string
    {
        $nodeDir = "{$this->runtimeDir}/node";
        $expectedBinName = str_starts_with($target, 'win-') ? 'node.exe' : 'node';

        // Check if a Node binary matching the *requested target* is already
        // installed. We can't just check `node` OR `node.exe` because on a
        // Windows dev box you'll have node.exe for local dev, and we want
        // cross-builds (e.g. --target=android-arm64) to redownload for the
        // correct arch rather than ship the wrong binary in the bundle.
        $existing = "{$nodeDir}/{$expectedBinName}";
        if (is_file($existing) && $this->nodeMatchesTarget($existing, $target)) {
            $this->components->twoColumnDetail('Node.js', 'already installed');

            return $this->findNodeBinary($nodeDir);
        }

        // For cross-targets (e.g. bundling android-arm64 on a Windows host)
        // wipe the node/ dir to avoid mixing architectures. Only wipe if it
        // has no usable lib/*.so from a previous Termux-deps install — those
        // libs are reusable across reinstalls and shouldn't be discarded on
        // every --force cycle when there's no node binary to even mismatch
        // against.
        if (is_dir($nodeDir) && ! is_file($existing)) {
            $hasLibs = is_dir("{$nodeDir}/lib") && count(array_diff(
                (array) @scandir("{$nodeDir}/lib"), ['.', '..']
            )) > 0;
            if (! $hasLibs) {
                $this->components->task('Clearing previous Node.js (arch mismatch)', function () use ($nodeDir) {
                    $this->rrmdir($nodeDir);

                    return true;
                });
            }
        }
        if (! is_dir($nodeDir)) {
            mkdir($nodeDir, 0755, true);
        }

        $url = $this->nodeDownloadUrl($target, $version);
        if (! $url) {
            $this->error("No Node.js build available for target: {$target}");

            return null;
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));
        $archive = "{$this->runtimeDir}/downloads/{$filename}";

        $this->components->task("Downloading Node.js {$version} ({$target})", function () use ($url, $archive) {
            return $this->download($url, $archive);
        });

        $this->components->task('Extracting Node.js', fn () => $this->extractNode($archive, $nodeDir, $target));

        // On Android, the Termux `node` binary depends on shared libraries
        // (libc++, openssl, c-ares, libicu, libsqlite) that ship in separate
        // Termux .deb packages. Without them, exec() returns ENOENT. We need
        // to download + extract these debs next to node/ so OpenWaManager can
        // inject the directory into LD_LIBRARY_PATH at launch time.
        if (str_starts_with($target, 'android-')) {
            $this->installTermuxDependencies($target, $nodeDir);
        }

        $candidate = $this->findNodeBinary($nodeDir);
        if (! $candidate || ! $this->nodeMatchesTarget($candidate, $target)) {
            $this->warn("Extracted node binary does not match target architecture ({$target}).");
        }

        return $candidate;
    }

    /**
     * Inspect the first bytes of a Node.js binary to verify it matches the
     * requested target architecture. Used to avoid shipping the wrong binary
     * in a cross-compilation scenario (e.g. bundling android-arm64 from a
     * Windows dev host that already has node.exe installed for local use).
     *
     * Returns true if the binary's file format matches the target family.
     */
    protected function nodeMatchesTarget(string $binPath, string $target): bool
    {
        if (! is_file($binPath)) {
            return false;
        }

        $fp = @fopen($binPath, 'rb');
        if (! $fp) {
            return false;
        }
        $magic = fread($fp, 8);
        fclose($fp);

        if ($magic === false || strlen($magic) < 4) {
            return false;
        }

        $isElf = substr($magic, 0, 4) === "\x7f\x45\x4c\x46";
        $isPe = substr($magic, 0, 2) === 'MZ';

        if (str_starts_with($target, 'win-')) {
            // Windows targets need a PE (MZ..) executable.
            return $isPe;
        }
        if (str_starts_with($target, 'android-') || str_starts_with($target, 'linux-') || str_starts_with($target, 'darwin-')) {
            // All unix targets need an ELF binary (yes, macOS app-bundles are
            // Mach-O, but we never install Mach-O here — only the linear ELF).
            // For our purposes, "non-PE" is correct because the macOS tarballs
            // currently download a Mach-O `node` binary which is also non-MZ.
            return ! $isPe && $isElf;
        }

        // Unknown target type — be permissive and assume it's correct.
        return true;
    }

    /**
     * Download + extract the Termux .deb packages that the Termux `node`
     * binary depends on. The .so files are placed into `nodeDir/lib/` so
     * they can be added to LD_LIBRARY_PATH at launch time.
     */
    protected function installTermuxDependencies(string $target, string $nodeDir): void
    {
        $archShort = $this->termuxArch($target);
        if (! $archShort) {
            $this->warn("Cannot resolve Termux arch for target {$target} — skipping dependency libs.");

            return;
        }

        $urls = $this->termuxDependencyUrls($archShort);
        $libDir = "{$nodeDir}/lib";
        if (! is_dir($libDir)) {
            mkdir($libDir, 0755, true);
        }
        $dlDir = "{$this->runtimeDir}/downloads";
        if (! is_dir($dlDir)) {
            mkdir($dlDir, 0755, true);
        }

        $alreadyInstalled = is_file("{$libDir}/libssl.so.3") && is_file("{$libDir}/libcrypto.so.3");
        if ($alreadyInstalled) {
            $this->components->twoColumnDetail('Termux libs', 'already installed');

            return;
        }

        foreach ($urls as $name => $url) {
            $debName = basename(parse_url($url, PHP_URL_PATH));
            // Sanitize the local filename: Termux encodes "epoch:version" as
            // "1:3.6.3", but ':' is illegal in Windows (and a stream separator
            // in NTFS). Replace with '-' so the file lands on disk correctly.
            $debName = str_replace(':', '-', $debName);
            $debPath = "{$dlDir}/termux-{$debName}";

            if (PHP_OS_FAMILY === 'Windows' && $debName === '') {
                // Defensive: if basename returned nothing on Windows due to
                // the ':', fall back to an explicit package-name based name.
                $debName = "termux-{$name}-".$archShort.'.deb';
                $debPath = "{$dlDir}/{$debName}";
            }

            if (! is_file($debPath)) {
                $ok = $this->components->task("Downloading Termux {$name}", fn () => $this->download($url, $debPath));
                if (! $ok) {
                    $this->warn("Failed to download {$name} — Node may fail to start on Android.");

                    continue;
                }
            }

            // Verify the .deb actually has content — empty downloads can happen
            // on flaky networks and silently leave the cache "installed".
            if (is_file($debPath) && filesize($debPath) < 1000) {
                $this->warn("Termux {$name} deb is suspiciously small (".filesize($debPath).' bytes); will redownload.');
                @unlink($debPath);
                $redownloaded = $this->components->task("Re-downloading Termux {$name}", fn () => $this->download($url, $debPath));
                if (! $redownloaded || ! is_file($debPath) || filesize($debPath) < 1000) {
                    $this->warn("Failed to re-download {$name}; Node may fail to start on Android.");

                    continue;
                }
            }

            $this->components->task("Extracting {$name}", fn () => $this->extractDebInto($debPath, $libDir));
        }
    }

    protected function nodeDownloadUrl(string $target, string $version): ?string
    {
        $map = [
            'win-x64' => ["https://nodejs.org/dist/v{$version}/node-v{$version}-win-x64.zip", 'zip'],
            'linux-x64' => ["https://nodejs.org/dist/v{$version}/node-v{$version}-linux-x64.tar.xz", 'txz'],
            'linux-arm64' => ["https://nodejs.org/dist/v{$version}/node-v{$version}-linux-arm64.tar.xz", 'txz'],
            'darwin-x64' => ["https://nodejs.org/dist/v{$version}/node-v{$version}-darwin-x64.tar.gz", 'tgz'],
            'darwin-arm64' => ["https://nodejs.org/dist/v{$version}/node-v{$version}-darwin-arm64.tar.gz", 'tgz'],
            // Android uses Termux's Bionic-compiled Node.js (.deb package).
            // The official nodejs.org linux-arm64 build is glibc-linked and
            // WILL NOT run on stock Android (Android uses Bionic libc, not glibc).
            // Termux ships Node.js built against Android's Bionic libc, so the
            // binary can be exec()'d directly from app-private storage.
            'android-arm64' => ["https://packages.termux.dev/apt/termux-main/pool/main/n/nodejs-lts/nodejs-lts_{$version}_aarch64.deb", 'deb'],
            'android-x86_64' => ["https://packages.termux.dev/apt/termux-main/pool/main/n/nodejs-lts/nodejs-lts_{$version}_x86_64.deb", 'deb'],
            'android-x86' => ["https://packages.termux.dev/apt/termux-main/pool/main/n/nodejs-lts/nodejs-lts_{$version}_i686.deb", 'deb'],
            'android-arm' => ["https://packages.termux.dev/apt/termux-main/pool/main/n/nodejs-lts/nodejs-lts_{$version}_arm.deb", 'deb'],
        ];

        return $map[$target][0] ?? null;
    }

    /**
     * Returns the archive format string ('zip','txz','tgz','deb') for a target,
     * or null if the target is unknown.
     */
    protected function nodeArchiveFormat(string $target): ?string
    {
        $map = [
            'win-x64' => 'zip',
            'linux-x64' => 'txz',
            'linux-arm64' => 'txz',
            'darwin-x64' => 'tgz',
            'darwin-arm64' => 'tgz',
            'android-arm64' => 'deb',
            'android-x86_64' => 'deb',
            'android-x86' => 'deb',
            'android-arm' => 'deb',
        ];

        return $map[$target] ?? null;
    }

    /**
     * The list of Termux .deb packages (URLs) required to run the Termux
     * `node` binary on stock Android. The node binary depends on these libs:
     *   libc++, openssl, c-ares, libicu, libsqlite, zlib
     *
     * We bundle them and inject their directory into LD_LIBRARY_PATH at
     * launch time, since Android's default linker doesn't search the
     * app-private storage path.
     *
     * @return array<string,string> Map of package-name => download URL
     */
    protected function termuxDependencyUrls(string $archShort): array
    {
        // Pinned versions confirmed in the Termux apt repo (2026-07).
        // zlib MUST be included: the Termux node binary links against
        // "libz.so.1", but stock Android only ships libz.so (wrong soname),
        // so the on-device linker fails with "library libz.so.1 not found".
        $packages = [
            'libc++' => ['libc++_29',          'libc++'],
            'openssl' => ['openssl_1:3.6.3',    'openssl'],
            'c-ares' => ['c-ares_1.34.8',      'c-ares'],
            'libicu' => ['libicu_78.3',        'libicu'],
            'libsqlite' => ['libsqlite_3.53.4',  'libsqlite'],
            'zlib' => ['zlib_1.3.2',         'zlib'],
        ];

        $base = 'https://packages.termux.dev/apt/termux-main/pool/main';
        $urls = [];
        foreach ($packages as $name => [$pathSuffix, $dirName]) {
            // Termux's Debian-style pool layout:
            //   - packages starting with "lib" use a 4-char prefix subdir
            //     (e.g. `libs/`, `libc/`, `libi/`)
            //   - all other packages use a 1-char prefix subdir (`o/`, `c/`)
            if (str_starts_with($name, 'lib')) {
                $poolSub = strtolower(substr($name, 0, 4));
            } else {
                $poolSub = strtolower(substr($name, 0, 1));
            }
            $urls[$name] = "{$base}/{$poolSub}/{$dirName}/{$pathSuffix}_{$archShort}.deb";
        }

        return $urls;
    }

    /**
     * Returns the short arch string Termux expects in its .deb filenames,
     * given our internal target name ('android-arm64' => 'aarch64', etc.).
     */
    protected function termuxArch(string $target): ?string
    {
        return [
            'android-arm64' => 'aarch64',
            'android-x86_64' => 'x86_64',
            'android-x86' => 'i686',
            'android-arm' => 'arm',
        ][$target] ?? null;
    }

    /**
     * Default Node.js versions for each target platform. Termux is pinned
     * separately because its build is updated independently of nodejs.org and
     * not every nodejs.org release is repackaged by Termux.
     */
    protected function defaultNodeVersion(string $target): string
    {
        // Termux nodejs-lts is currently at 24.18.0 (as of 2026-07).
        if (str_starts_with($target, 'android-')) {
            return '24.18.0';
        }

        return '22.14.0';
    }

    protected function extractNode(string $archive, string $nodeDir, string $target): bool
    {
        $tmp = "{$this->runtimeDir}/downloads/_node_extract";
        if (is_dir($tmp)) {
            $this->rrmdir($tmp);
        }
        mkdir($tmp, 0755, true);

        if (str_ends_with($archive, '.deb')) {
            // .deb = `ar` archive containing data.tar.xz (or data.tar.gz)
            $ok = $this->extractDebInto($archive, $tmp);
            if (! $ok) {
                $this->rrmdir($tmp);

                return false;
            }
            // data.tar.xz extracted files into $tmp; move them to $tmp/root
            // so the consistent logic below can find them. Termux layout is:
            //   ./data/data/com.termux/files/usr/bin/node
            //   ./data/data/com.termux/files/usr/lib/*.so -> not needed here
            // The recursive scanner below will find node at any depth.
        } elseif (str_ends_with($archive, '.zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($archive) !== true) {
                return false;
            }
            $zip->extractTo($tmp);
            $zip->close();
        } elseif (str_ends_with($archive, '.tar.xz') || str_ends_with($archive, '.txz')) {
            // .tar.xz: first decompress to .tar, then extract with PharData
            $tarPath = substr($archive, 0, -3); // strip .xz
            if (! file_exists($tarPath)) {
                exec('xz -d -k -f "'.$archive.'" 2>NUL', $xzOut, $xzCode);
                if ($xzCode !== 0) {
                    // Fallback: try using 7-Zip
                    exec('7z x -y -o"'.$tmp.'" "'.$archive.'" 2>NUL', $szOut, $szCode);
                    if ($szCode !== 0) {
                        return false;
                    }
                    $tarPath = null;
                }
            }
            if ($tarPath && file_exists($tarPath)) {
                $phar = new \PharData($tarPath);
                $phar->extractTo($tmp);
                @unlink($tarPath);
            }
        } elseif (str_ends_with($archive, '.tar.gz') || str_ends_with($archive, '.tgz')) {
            try {
                $phar = new \PharData($archive);
                $phar->extractTo($tmp);
            } catch (\Throwable $e) {
                // gzip fallback: decompress to tar, then extract
                $tarPath = str_ends_with($archive, '.gz')
                    ? substr($archive, 0, -3)
                    : $archive.'.tar';
                if (! file_exists($tarPath)) {
                    $gz = gzopen($archive, 'rb');
                    $out = fopen($tarPath, 'wb');
                    while (! gzeof($gz)) {
                        fwrite($out, gzread($gz, 8192));
                    }
                    gzclose($gz);
                    fclose($out);
                }
                if (file_exists($tarPath)) {
                    $phar = new \PharData($tarPath);
                    $phar->extractTo($tmp);
                    @unlink($tarPath);
                }
            }
        } else {
            // Plain .tar or unknown — try PharData directly
            $phar = new \PharData($archive);
            $phar->extractTo($tmp);
        }

        // Find the `node` (or `node.exe`) binary anywhere under $tmp.
        // Works for both the standard node-tarball layout (`node-vX/bin/node`)
        // and the Termux .deb layout (`./data/data/com.termux/files/usr/bin/node`).
        //
        // CRITICAL: use the TARGET's binary name, not the host's. A Windows
        // dev box building for android-arm64 must look for `node` (Linux ELF)
        // inside the Termux deb, NOT for `node.exe` (which is only inside the
        // Windows nodejs.org zip).
        $isWindowsTarget = str_starts_with($target, 'win-');
        $expectedName = $isWindowsTarget ? 'node.exe' : 'node';
        $nodeBinary = $this->findFileRecursive($tmp, $expectedName);
        if (! $nodeBinary) {
            $this->rrmdir($tmp);

            return false;
        }

        $destNode = $nodeDir.'/'.basename($nodeBinary);
        if (! is_dir($nodeDir)) {
            mkdir($nodeDir, 0755, true);
        }
        copy($nodeBinary, $destNode);
        if (PHP_OS_FAMILY !== 'Windows') {
            @chmod($destNode, 0755);
        }

        // For Termux debs, also copy shared libs to nodeDir/lib so OpenWaManager
        // can wire LD_LIBRARY_PATH to that directory at launch time. The deps
        // live at ./data/data/com.termux/files/usr/lib/*.so in the node deb
        // itself for some builds, or in the separate lib debs. We do the heavy
        // lifting in installTermuxDependencies(), but copy any .so files found
        // in the node deb itself here as a convenience.
        $libDir = "{$nodeDir}/lib";
        if (! is_dir($libDir)) {
            mkdir($libDir, 0755, true);
        }
        $usrLibDir = dirname($nodeBinary).'/../lib';
        if (is_dir($usrLibDir)) {
            $realLibDir = realpath($usrLibDir);
            if ($realLibDir && is_dir($realLibDir)) {
                foreach (scandir($realLibDir) as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    if (! str_ends_with($entry, '.so') && ! preg_match('/\.so\.\d+/', $entry)) {
                        continue;
                    }
                    copy("{$realLibDir}/{$entry}", "{$libDir}/{$entry}");
                }
            }
        }

        $this->rrmdir($tmp);

        return is_file($destNode);
    }

    /**
     * Extract the `data.tar.xz` (or `.gz`) contained inside a .deb archive
     * into the given target directory. Returns true on success.
     *
     * A .deb is an `ar` archive with three members:
     *   - debian-binary  (plain text, version line)
     *   - control.tar.xz (package metadata)
     *   - data.tar.xz     (the real files we want)
     *
     * We don't depend on the `ar` or `dpkg-deb` binaries — we parse the `ar`
     * format manually (it's a simple fixed-offset header format) to support
     * Windows hosts that lack those POSIX tools.
     */
    protected function extractDebInto(string $debPath, string $destDir): bool
    {
        if (! is_file($debPath)) {
            return false;
        }
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $fp = @fopen($debPath, 'rb');
        if (! $fp) {
            return false;
        }

        // ar magic: "!<arch>\n" (8 bytes)
        $magic = fread($fp, 8);
        if ($magic !== "!<arch>\n") {
            fclose($fp);

            return false;
        }

        $dataTarName = null;
        $dataTarOffset = null;
        $dataTarSize = null;

        // Each subsequent member is a 60-byte header followed by file data.
        while (! feof($fp)) {
            $header = fread($fp, 60);
            if (strlen($header) < 60) {
                break;
            }

            // Header layout (60 bytes):
            //   0-15  filename (16)
            //  16-27  mtime    (12)
            //  28-33  owner    (6)
            //  34-39  group    (6)
            //  40-47  mode     (8)
            //  48-59  size     (10) decimal
            $sizeRaw = trim(substr($header, 48, 10));
            $size = (int) $sizeRaw;
            $name = trim(substr($header, 0, 16));
            // Strip trailing spaces and the "/" terminator ar uses.
            $name = rtrim($name, "/ \t");

            // Position after header read: at the start of file data.
            $dataStart = ftell($fp);

            // We want data.tar.xz OR data.tar.gz (in that order).
            if ($name === 'data.tar.xz' || $name === 'data.tar.gz' || $name === 'data.tar.bz2' || $name === 'data.tar.lzma') {
                $dataTarName = $name;
                $dataTarOffset = $dataStart;
                $dataTarSize = $size;
                break;
            }

            // Skip file content, align to 2-byte boundary (ar convention).
            $skip = $size + ($size % 2);
            fseek($fp, $dataStart + $skip, SEEK_SET);
        }

        if ($dataTarName === null) {
            fclose($fp);

            return false;
        }

        // Stream the data.tar.* archive out to a temp file.
        fseek($fp, $dataTarOffset, SEEK_SET);
        $tmpTar = $destDir.'/.'.basename($dataTarName).'.tmp';
        $out = fopen($tmpTar, 'wb');
        $left = $dataTarSize;
        while ($left > 0 && ! feof($fp)) {
            $buf = fread($fp, min(1 << 20, $left));
            if ($buf === false || $buf === '') {
                break;
            }
            fwrite($out, $buf);
            $left -= strlen($buf);
        }
        fclose($out);
        fclose($fp);

        // Decompress (if needed) and extract the data.tar archive.
        try {
            if (str_ends_with($dataTarName, '.xz')) {
                // $tmpTar has the form ".../.data.tar.xz.tmp"; we want the
                // decompressed file to end in ".tar" (PharData/tar binary
                // both key off the extension). Strip the trailing ".tmp"
                // and the ".xz" suffix, then append ".tar".
                $decompressed = preg_replace('/\.xz\.tmp$/', '.tar', $tmpTar);
                if (! file_exists($decompressed)) {
                    $xzCode = $this->ensureXzDecompress($tmpTar, $decompressed);
                    if ($xzCode !== 0 && ! is_file($decompressed)) {
                        @unlink($tmpTar);
                        @unlink($decompressed);

                        return false;
                    }
                }
                if (! file_exists($decompressed)) {
                    @unlink($tmpTar);

                    return false;
                }
                if (! $this->safeTarExtract($decompressed, $destDir)) {
                    @unlink($decompressed);
                    @unlink($tmpTar);

                    return false;
                }
                @unlink($decompressed);
            } elseif (str_ends_with($dataTarName, '.gz')) {
                $decompressed = preg_replace('/\.gz\.tmp$/', '.tar', $tmpTar);
                if (! file_exists($decompressed)) {
                    $gz = gzopen($tmpTar, 'rb');
                    $out2 = fopen($decompressed, 'wb');
                    while (! gzeof($gz)) {
                        fwrite($out2, gzread($gz, 1 << 20));
                    }
                    gzclose($gz);
                    fclose($out2);
                }
                if (! $this->safeTarExtract($decompressed, $destDir)) {
                    @unlink($decompressed);
                    @unlink($tmpTar);

                    return false;
                }
                @unlink($decompressed);
            } elseif (str_ends_with($dataTarName, '.bz2')) {
                $decompressed = preg_replace('/\.bz2\.tmp$/', '.tar', $tmpTar);
                if (! file_exists($decompressed)) {
                    $bz = bzopen($tmpTar, 'r');
                    $out2 = fopen($decompressed, 'wb');
                    while (! feof($bz)) {
                        fwrite($out2, bzread($bz, 1 << 20));
                    }
                    bzclose($bz);
                    fclose($out2);
                }
                if (! $this->safeTarExtract($decompressed, $destDir)) {
                    @unlink($decompressed);
                    @unlink($tmpTar);

                    return false;
                }
                @unlink($decompressed);
            } else {
                // Plain tar.
                if (! $this->safeTarExtract($tmpTar, $destDir)) {
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
     * Safely extract a .tar file. Works around several PharData bugs:
     *   - On Windows, PharData::extractTo() fails with "Cannot extract '.',
     *     internal error" because the tar archive contains `.` and `..`
     *     entries that PharData refuses to write (security protection).
     *   - On Windows, some PharData builds can't iterate over tar entries
     *     with `RecursiveIteratorIterator` (`count()` reports 208 but
     *     `foreach` returns 0 items).
     *
     * Strategy (first to succeed wins):
     *   1. Use `tar` binary if available (Windows ships bsdtar in System32;
     *      Linux/macOS has GNU tar / BSD tar). Tar natively handles `.` and
     *      `..` entries correctly.
     *   2. Fall back to PharData::extractTo() with the third argument set to
     *      false (don't overwrite, in case the dot-entry is the source of the
     *      PharException). Skip on the file-by-file loop only if direct
     *      extractTo succeeds.
     *
     * Returns true if extraction produced any files under $destDir.
     */
    protected function safeTarExtract(string $tarPath, string $destDir): bool
    {
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        if (! is_file($tarPath)) {
            return false;
        }

        // Strategy 1: invoke the `tar` binary.
        $tarBin = null;
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows 10+ ships System32\tar.exe (bsdtar 3.x).
            if (@is_file('C:\Windows\System32\tar.exe')) {
                $tarBin = 'C:\Windows\System32\tar.exe';
            } else {
                exec('where tar 2>NUL', $out, $code);
                if ($code === 0 && ! empty($out[0])) {
                    $tarBin = trim($out[0]);
                }
            }
        } else {
            exec('command -v tar 2>/dev/null', $out, $code);
            if ($code === 0 && ! empty($out[0])) {
                $tarBin = trim($out[0]);
            }
        }

        if ($tarBin !== null) {
            $cmd = '"'.$tarBin.'" -xf "'.$tarPath.'" -C "'.$destDir.'"';
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd .= ' 2>NUL';
            } else {
                $cmd .= ' 2>/dev/null';
            }
            exec($cmd, $out, $code);
            // Tar may exit non-zero on harmless warnings (e.g. unable to
            // create a symlink on a non-POSIX fs). Check if it actually
            // extracted anything before deciding success.
            if ($this->dirHasAnyFile($destDir)) {
                return true;
            }
        }

        // Strategy 2: fall back to PharData.
        try {
            $phar = new \PharData($tarPath);
            $phar->extractTo($destDir, overwrite: true);
            if ($this->dirHasAnyFile($destDir)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Swallow; one more fallback below.
        }

        // Strategy 3: PharData with file-by-file iteration (skip dot entries).
        try {
            $phar = new \PharData($tarPath);
            $count = 0;
            foreach ($phar as $key => $file) {
                $count++;
                if ($key === '.' || $key === '..') {
                    continue;
                }
                // $file is a PharFileInfo (or DirectoryEntry). Use full path.
                $relative = ltrim((string) $key, './');
                if ($relative === '' || str_starts_with($relative, '..')) {
                    continue;
                }
                $target = rtrim($destDir, '/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
                if ($file->isDir()) {
                    @mkdir($target, 0755, true);
                } else {
                    @mkdir(dirname($target), 0755, true);
                    copy('phar://'.$tarPath.'/'.ltrim((string) $key, '/'), $target);
                }
            }

            return $this->dirHasAnyFile($destDir);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Returns true if $dir contains at least one file (recursively).
     */
    protected function dirHasAnyFile(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }
        try {
            $rii = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($rii as $f) {
                if ($f->isFile()) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    /**
     * Decompress a .xz file to a target .tar path using the most reliable
     * strategy available on this host. Returns 0 on success (xz convention).
     *
     * Strategy (first to succeed wins):
     *   1. PATH-resident `xz` (via `xz -d -k -f -c <in>` to a descriptor pipe).
     *   2. Git for Windows / MSYS2 / Cygwin bundled xz.exe (tryBundledXz()).
     *   3. PECL lzma extension if loaded (xzdecrypt()).
     *   4. Python stdlib lzma module (xzDecompressToFile()), which throws if
     *      Python is missing — the caller's try/catch surfaces a clean error.
     *
     * The output file is always written with a ".tar" suffix so downstream
     * PharData / tar-binary extraction recognises the format.
     */
    protected function ensureXzDecompress(string $xzPath, string $outPath): int
    {
        if (is_file($outPath) && filesize($outPath) > 0) {
            return 0;
        }

        // Strategy 1: PATH-resident xz (only the redirect form, never in-place,
        // because in-place destroys the source .xz on hosts where xz literally
        // removes the input after decompression).
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where xz 2>NUL', $xzWhere, $xzWhereCode);
            if ($xzWhereCode === 0 && ! empty($xzWhere[0])) {
                $xzBin = trim($xzWhere[0]);
                $code = $this->runXzTo($xzBin, $xzPath, $outPath);
                if ($code === 0 && is_file($outPath) && filesize($outPath) > 0) {
                    return 0;
                }
            }
        } else {
            $xzBin = trim((string) @shell_exec('command -v xz 2>/dev/null'));
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
     * Returns 0 on success, non-zero otherwise.
     */
    protected function runXzTo(string $xzBin, string $inPath, string $outPath): int
    {
        $cmd = '"'.$xzBin.'" -d -k -f -c "'.$inPath.'"';
        $descriptors = [
            0 => ['file', 'nul', 'r'],
            1 => ['file', str_replace('/', DIRECTORY_SEPARATOR, $outPath), 'wb'],
            2 => ['file', 'nul', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($proc)) {
            return 1;
        }
        proc_close($proc);

        return is_file($outPath) && filesize($outPath) > 0 ? 0 : 1;
    }

    /**
     * Look for a bundled `xz` executable in well-known locations on Windows
     * (Git for Windows, MSYS2, Cygwin) and use it to decompress the file.
     * Returns 0 on success (matching the `xz` exit-code convention so
     * callers can do `if ($code !== 0)`).
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
                $cmd = '"'.$candidate.'" -d -k -f -c "'.$xzPath.'"';
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
     * Pure-PHP XZ decompressor fallback. Reads `.xz` file, writes `.tar`.
     * Used when neither the `xz` binary nor Git's bundled `xz.exe` is
     * available on the host.
     *
     * Strategy (first to succeed wins):
     *   1. PHP's ext-lzma `xzdecrypt()` function (PECL lzma extension).
     *   2. Python via `python -c` using the stdlib `lzma` module.
     *   3. PHP's built-in PharData if PHP was compiled with liblzma.
     *
     * Throws RuntimeException if all strategies fail.
     */
    protected function xzDecompressToFile(string $xzPath, string $tarPath): void
    {
        // Strategy 1: PHP's ext-lzma (PECL lzma) — usually absent.
        if (function_exists('xzdecrypt')) {
            $data = file_get_contents($xzPath);
            $decompressed = xzdecrypt($data);
            if ($decompressed !== false && $decompressed !== '') {
                file_put_contents($tarPath, $decompressed);

                return;
            }
        }

        // Strategy 2: Python's stdlib `lzma` module — almost always present
        // on Windows 10+ (Microsoft Store Python) and Linux/macOS.
        $python = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
        $pyScript = <<<'PY'
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
        $tmpPy = sys_get_temp_dir().'/mgs_xz_decompress_'.bin2hex(random_bytes(4)).'.py';
        file_put_contents($tmpPy, $pyScript);
        $cmd = escapeshellarg($python).' '.escapeshellarg($tmpPy).' '.escapeshellarg($xzPath).' '.escapeshellarg($tarPath);
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

        // Strategy 3: give up with a helpful error.
        throw new \RuntimeException(
            'xz decompression failed: could not find `xz` on PATH, in Git for '.
            'Windows, or via the Python `lzma` module. To fix this on Windows, '.
            'install Git for Windows (https://git-scm.com/win) which ships '.
            'xz.exe, or install Python (https://python.org) which provides '.
            'the `lzma` stdlib module. Then re-run the command.'
        );
    }

    /**
     * Recursively find a file by name under a directory. Returns the full
     * path or null. Used to locate `node` inside Termux deb extraction
     * directories (which are deeply nested).
     */
    protected function findFileRecursive(string $dir, string $filename): ?string
    {
        if (! is_dir($dir)) {
            return null;
        }
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
     * Locate the installed Node binary in nodeDir. The filename depends on the
     * TARGET platform, not the host: a Windows dev box cross-compiling for
     * android-arm64 extracts a Linux ELF named `node`, so checking
     * PHP_OS_FAMILY here would (incorrectly) look for `node.exe`.
     * Check for both names; `nodeMatchesTarget()` in the caller validates
     * that the found binary matches the requested target architecture.
     */
    protected function findNodeBinary(string $nodeDir): ?string
    {
        if (is_file("{$nodeDir}/node")) {
            return "{$nodeDir}/node";
        }
        if (is_file("{$nodeDir}/node.exe")) {
            return "{$nodeDir}/node.exe";
        }

        return null;
    }

    protected function installOpenwa(): ?string
    {
        $appDir = "{$this->runtimeDir}/app";

        if (is_file("{$appDir}/package.json") && ! $this->option('force')) {
            $this->components->twoColumnDetail('OpenWA app', 'already installed');

            return $appDir;
        }

        $fromPath = $this->option('from');
        if ($fromPath) {
            return $this->installFromLocal($fromPath, $appDir);
        }

        return $this->installFromGitHub($appDir);
    }

    protected function installFromLocal(string $source, string $appDir): ?string
    {
        $source = rtrim($source, '\\/');

        if (! is_dir($source)) {
            $this->error("Source path does not exist: {$source}");

            return null;
        }

        if (! is_file("{$source}/package.json")) {
            $this->error("No package.json found at {$source} — not a valid OpenWA installation");

            return null;
        }

        $this->components->task("Copying OpenWA from {$source}", function () use ($source, $appDir) {
            if (is_dir($appDir)) {
                $this->rrmdir($appDir);
            }
            $this->rcopy($source, $appDir);

            return true;
        });

        if (! is_file("{$appDir}/package.json")) {
            $this->error('Failed to copy OpenWA from local path');

            return null;
        }

        $this->components->twoColumnDetail('Copied from', $source);

        return $appDir;
    }

    protected function installFromGitHub(string $appDir): ?string
    {
        $version = $this->option('openwa-version');
        $url = $version
            ? "https://github.com/rmyndharis/OpenWA/archive/refs/tags/{$version}.zip"
            : 'https://github.com/rmyndharis/OpenWA/archive/refs/heads/main.zip';

        $archive = "{$this->runtimeDir}/downloads/openwa-source.zip";

        $this->components->task('Downloading OpenWA source', function () use ($url, $archive) {
            return $this->download($url, $archive);
        });

        $this->components->task('Extracting OpenWA source', function () use ($archive, $appDir) {
            $tmp = "{$this->runtimeDir}/downloads/_openwa_extract";
            if (is_dir($tmp)) {
                $this->rrmdir($tmp);
            }
            mkdir($tmp, 0755, true);

            $zip = new \ZipArchive;
            if ($zip->open($archive) !== true) {
                return false;
            }
            $zip->extractTo($tmp);
            $zip->close();

            $entries = scandir($tmp);
            $subDir = null;
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..' && is_dir("{$tmp}/{$entry}")) {
                    $subDir = "{$tmp}/{$entry}";
                    break;
                }
            }

            if (! $subDir) {
                return false;
            }

            $this->rcopy($subDir, $appDir);
            $this->rrmdir($tmp);

            return true;
        });

        if (! is_file("{$appDir}/package.json")) {
            $this->error('OpenWA source extraction failed — package.json not found');

            return null;
        }

        return $appDir;
    }

    protected function installDependencies(string $appDir): void
    {
        $modulesDir = "{$this->runtimeDir}/modules";

        if (is_dir("{$modulesDir}/node_modules") && count(scandir("{$modulesDir}/node_modules")) > 2) {
            $this->components->twoColumnDetail('npm dependencies', 'already installed from modules');

            return;
        }

        // Check if node_modules already exists in the app dir (from local copy)
        $appNm = "{$appDir}/node_modules";
        if (is_dir($appNm) && count(scandir($appNm)) > 2) {
            $this->components->task('Moving existing node_modules to modules/', function () use ($appNm, $modulesDir) {
                $this->renameNodeModules($appNm, $modulesDir);

                return true;
            });

            return;
        }

        $this->components->task('Installing npm dependencies', function () use ($appDir, $modulesDir, $appNm) {
            // Full install (incl. devDependencies) is required for `nest build`.
            // --ignore-scripts skips the OpenWA dashboard postinstall hook.
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = sprintf(
                    'cd /D "%s" && npm install --prefix "%s" --ignore-scripts 2>&1',
                    $appDir,
                    $appDir
                );
            } else {
                $cmd = sprintf(
                    'cd "%s" && npm install --prefix "%s" --ignore-scripts 2>&1',
                    $appDir,
                    $appDir
                );
            }
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                Log::warning('OpenWA npm install failed', ['output' => implode("\n", $output)]);

                return false;
            }

            if (is_dir($appNm)) {
                $this->renameNodeModules($appNm, $modulesDir);
            }

            return true;
        });
    }

    protected function buildOpenwa(string $appDir): void
    {
        if (is_file("{$appDir}/dist/main.js")) {
            $this->components->twoColumnDetail('OpenWA build', 'dist/main.js already exists');

            return;
        }

        $modulesDir = "{$this->runtimeDir}/modules";
        $nodeModules = is_dir("{$appDir}/node_modules")
            ? "{$appDir}/node_modules"
            : (is_dir($modulesDir) ? $modulesDir : null);

        if ($nodeModules && ! is_dir("{$appDir}/node_modules")) {
            $this->linkNodeModules($appDir, $nodeModules);
        }

        $built = $this->components->task('Building OpenWA (nest build)', function () use ($appDir) {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = sprintf('cd /D "%s" && npm run build 2>&1', $appDir);
            } else {
                $cmd = sprintf('cd "%s" && npm run build 2>&1', $appDir);
            }
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                Log::warning('OpenWA build failed', ['output' => implode("\n", array_slice($output, -30))]);

                return false;
            }

            return is_file("{$appDir}/dist/main.js");
        });

        if (! $built) {
            $this->error('OpenWA build failed — dist/main.js was not created.');
            $this->warn('Try building manually: cd "'.$appDir.'" && npm install --ignore-scripts && npm run build');

            return;
        }

        $this->components->task('Pruning dev dependencies', function () use ($appDir, $modulesDir) {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = sprintf('cd /D "%s" && npm prune --omit=dev 2>&1', $appDir);
            } else {
                $cmd = sprintf('cd "%s" && npm prune --omit=dev 2>&1', $appDir);
            }
            exec($cmd, $output, $exitCode);

            $appNm = "{$appDir}/node_modules";
            if (is_dir($appNm)) {
                $this->renameNodeModules($appNm, $modulesDir);
            } elseif (is_dir($modulesDir)) {
                $this->linkNodeModules($appDir, $modulesDir);
            }

            return true;
        });
    }

    protected function linkNodeModules(string $appDir, string $modulesDir): void
    {
        $link = "{$appDir}/node_modules";

        if (is_link($link) || is_dir($link)) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec(sprintf('mklink /J "%s" "%s" 2>NUL', $link, $modulesDir));
        } else {
            @symlink($modulesDir, $link);
        }
    }

    protected function renameNodeModules(string $source, string $dest): void
    {
        if (is_dir($dest)) {
            $this->rrmdir($dest);
        }

        rename($source, $dest);

        $stubModules = "{$dest}/node_modules";
        if (is_dir($stubModules)) {
            $this->rrmdir($stubModules);
        }
    }

    protected function generateApiKey(): string
    {
        return 'mgs_'.bin2hex(random_bytes(24));
    }

    protected function writeEnvConfig(string $appDir, string $nodeBinary, string $apiKey): void
    {
        $envPath = base_path('.env');
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        $replacements = [
            'OPENWA_API_KEY' => $apiKey,
            'OPENWA_BINARY_DIR' => $appDir,
            'OPENWA_NODE_BINARY' => $nodeBinary,
            'OPENWA_AUTO_START' => 'true',
            'OPENWA_BASE_URL' => 'http://127.0.0.1:2785',
        ];

        foreach ($replacements as $key => $value) {
            $escaped = str_replace('\\', '\\\\', $value);
            // Quote values that contain whitespace (e.g. Windows paths like
            // "D:\Mobile App\MGS\...") — Laravel's Dotenv parser rejects
            // unquoted whitespace mid-value. Quotes are preserved only when
            // needed to keep backward compatibility with simple values.
            if (preg_match('/\s/', $escaped)) {
                $escaped = '"'.str_replace('"', '\\"', $escaped).'"';
            }
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $envContent);
            } else {
                $envContent .= "\n{$key}={$escaped}";
            }
        }

        file_put_contents($envPath, $envContent);
        $this->components->twoColumnDetail('.env updated', count($replacements).' entries');
    }

    protected function download(string $url, string $dest): bool
    {
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($dest, 'w+');
        if (! $fp) {
            return false;
        }

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
        $error = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        fclose($fp);

        if (! $ok || $httpCode >= 400) {
            @unlink($dest);
            Log::error('Download failed', ['url' => $url, 'http' => $httpCode, 'err' => $error]);

            return false;
        }

        $fileSize = filesize($dest);
        if ($fileSize < 1024) {
            @unlink($dest);
            Log::error('Downloaded file too small (likely an error page)', [
                'url' => $url, 'size' => $fileSize,
            ]);

            return false;
        }

        if ($contentType && str_contains($contentType, 'text/html')) {
            $content = file_get_contents($dest);
            if (str_contains((string) $content, '<html') || str_contains((string) $content, '<!DOCTYPE')) {
                @unlink($dest);
                Log::error('Downloaded an HTML page instead of a binary', ['url' => $url]);

                return false;
            }
        }

        return true;
    }

    protected function rcopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === 'node_modules') {
                continue;
            }
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
        if (! is_dir($dir)) {
            return;
        }

        // On Windows, RecursiveDirectoryIterator fails to descend into
        // junctions/symlinks (`node_modules` often has them) and unlink()
        // fails when files are held open by antivirus / Windows Search.
        // Shell out to `rd /s /q` which handles all of that natively.
        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = str_replace('/', DIRECTORY_SEPARATOR, $dir);
            exec('rd /s /q "'.$normalized.'" 2>NUL', $o, $code);
            if (! is_dir($dir)) {
                return;
            }
            // Fall through to the manual walk if `rd` failed.
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir() && ! is_link($item->getPathname())) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }
        @rmdir($dir);
    }
}
