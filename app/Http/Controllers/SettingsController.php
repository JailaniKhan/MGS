<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WhatsApp\OpenWaManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'company_name' => Setting::get('company_name', 'My Business'),
            'company_address' => Setting::get('company_address', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_email' => Setting::get('company_email', ''),
            'invoice_prefix' => Setting::get('invoice_prefix', 'INV-'),
            'currency' => Setting::get('currency', 'AFN'),
            'language' => Setting::get('language', 'ps'),
        ];

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email|max:255',
            'invoice_prefix' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:3',
            'language' => 'nullable|string|in:en,ps,fa',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->route('settings.index')->with('success', __('messages.saved_successfully'));
    }

    public function updateLanguage(Request $request)
    {
        $validated = $request->validate([
            'language' => 'required|string|in:en,ps,fa',
        ]);

        Setting::set('language', $validated['language']);

        return redirect()->back()->with('success', __('messages.saved_successfully'));
    }

    /**
     * Show the OpenWA WhatsApp gateway connection status and a live QR code.
     * The QR is fetched on demand from the self-hosted OpenWA gateway so it
     * can be scanned directly inside the app.
     */
    public function openwa(\App\Services\WhatsApp\OpenWaService $openWa, OpenWaManager $manager)
    {
        $configured = $openWa->isConfigured();
        ['gatewayRunning' => $gatewayRunning, 'status' => $status, 'qr' => $qr] =
            $configured
                ? $this->resolveOpenwaState($openWa, $manager)
                : ['gatewayRunning' => null, 'status' => null, 'qr' => null];

        $managerStatus = $manager->status();

        return view('settings.openwa', compact('configured', 'gatewayRunning', 'status', 'qr', 'managerStatus'));
    }

    /**
     * Resolve the current gateway/session state with the fewest possible
     * HTTP round-trips. When the gateway is up, each poll does:
     *   1. one /api/health check (inside isRunning),
     *   2. one /api/sessions/{id} GET,
     *   3. one /api/sessions/{id}/qr GET.
     * The gateway's event loop is busy with Baileys crypto while the QR
     * rotates, so each call takes ~40-140ms — 4 sequential calls (the old
     * flow) added up to ~500ms per poll.
     *
     * @return array{gateway_running: bool, status: ?array, qr: ?string}
     */
    protected function resolveOpenwaState(\App\Services\WhatsApp\OpenWaService $openWa, OpenWaManager $manager): array
    {
        if (!$manager->isRunning()) {
            $manager->ensureStarted();
        }

        $gatewayRunning = $manager->isRunning();
        $status = $gatewayRunning ? $openWa->sessionStatus() : null;
        $state = $status['status'] ?? null;

        // Session is in-memory in the gateway, so a fresh gateway (or one
        // that crashed and was relaunched) has no session at all. Kick it
        // only then — never while a QR is waiting, or it would rotate again.
        // disconnected (QR expired without a scan, socket closed) is also
        // kickable: the gateway clears s.qr on close, so there is nothing
        // live to destroy.
        if ($gatewayRunning && in_array($state, ['not_found', 'dead', 'disconnected'], true)) {
            $manager->startSession();
            $status = $openWa->sessionStatus();
            $state = $status['status'] ?? null;
        }

        // Try to fetch the QR for any state that isn't connected/dead. The
        // gateway reports "qr_ready" slightly before the base64 data URL is
        // ready and keeps a stale QR around after "disconnected", so a
        // strict status check would hide a perfectly scannable QR.
        $qr = $status && !in_array($state, ['ready', 'connected', 'dead'], true)
            ? $openWa->qrCode()
            : null;

        return compact('gatewayRunning', 'status', 'qr');
    }

    /**
     * AJAX endpoint that returns a fresh QR code + session status as JSON,
     * so the settings page can refresh without a full reload.
     */
    public function openwaQr(\App\Services\WhatsApp\OpenWaService $openWa, OpenWaManager $manager)
    {
        if (!$openWa->isConfigured()) {
            return response()->json(['configured' => false], 200);
        }

        ['gatewayRunning' => $gatewayRunning, 'status' => $status, 'qr' => $qr] =
            $this->resolveOpenwaState($openWa, $manager);

        $managerStatus = $manager->status();

        return response()->json([
            'configured' => true,
            'gateway_running' => $gatewayRunning,
            'status' => $status['status'] ?? null,
            'qr' => $qr,
            'last_error' => $gatewayRunning ? null : $managerStatus['last_error'],
            // Structured reason the session dropped (401 = rejected creds,
            // 408 = network/DNS or QR-expiry) so the UI can show the right
            // guidance instead of a generic "connection closed".
            'last_disconnect' => $status['lastDisconnect'] ?? null,
            // Current pairing code (if still valid) + expiry so the UI can
            // count down and auto-refresh instead of showing a dead code.
            'pairing' => $status['pairing'] ?? null,
            'baileys_log_tail' => $managerStatus['baileys_log_tail'] ?? '',
        ]);
    }

    /**
     * Generate an 8-character pairing code (alternative to scanning the QR)
     * so the session can be linked by typing the code into WhatsApp.
     */
    public function openwaPairingCode(Request $request, \App\Services\WhatsApp\OpenWaService $openWa)
    {
        if (!$openWa->isConfigured()) {
            return response()->json(['configured' => false], 200);
        }

        $validated = $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{6,15}$/',
        ]);

        $pairing = $openWa->requestPairingCode($validated['phone']);

        if (!$pairing || empty($pairing['pairingCode'])) {
            return response()->json(['error' => 'Could not generate pairing code.'], 422);
        }

        return response()->json([
            'pairingCode' => $pairing['pairingCode'],
            'expiresAt' => $pairing['expiresAt'],
        ]);
    }

    /**
     * Poll the current pairing code state (code + expiry). Returns null code
     * once the code has expired so the page can automatically request a new
     * one without the user noticing a dead code on screen.
     */
    public function openwaPairingStatus(\App\Services\WhatsApp\OpenWaService $openWa)
    {
        if (!$openWa->isConfigured()) {
            return response()->json(['configured' => false], 200);
        }

        $pairing = $openWa->pairingStatus();

        return response()->json([
            'pairingCode' => $pairing['pairingCode'] ?? null,
            'expiresAt' => $pairing['expiresAt'] ?? null,
        ]);
    }

    /**
     * Restart the OpenWA gateway process (stop then start).
     */
    public function openwaRestart(OpenWaManager $manager)
    {
        $restarted = $manager->restart();

        return response()->json([
            'success' => $restarted,
            'running' => $manager->isRunning(),
        ]);
    }

    /**
     * Send a test WhatsApp message via the connected OpenWA gateway so the
     * user can verify delivery without running the test suite.
     */
    public function openwaTestSend(\App\Services\WhatsApp\OpenWaService $openWa, OpenWaManager $manager)
    {
        if (!$openWa->isConfigured()) {
            return response()->json(['success' => false, 'error' => 'OpenWA is not configured.'], 200);
        }

        if (!$manager->isRunning()) {
            return response()->json([
                'success' => false,
                'error' => __('messages.openwa_unreachable_test') ?? 'OpenWA gateway is not running. Start it first.',
            ], 200);
        }

        $status = $openWa->sessionStatus();

        // null status = gateway down / unreachable.
        if ($status === null) {
            return response()->json([
                'success' => false,
                'error' => __('messages.openwa_unreachable_test') ?? 'OpenWA gateway is not reachable. Start it first.',
            ], 200);
        }

        if (($status['status'] ?? null) !== 'ready' && ($status['status'] ?? null) !== 'connected') {
            return response()->json([
                'success' => false,
                'error' => 'Session is not connected (state: ' . ($status['status'] ?? 'unknown') . '). Link the device first.',
            ], 200);
        }

        $phone = $status['phone'] ?? config('services.openwa.test_chat_id');

        if (!$phone) {
            return response()->json(['success' => false, 'error' => 'No connected phone number found.'], 200);
        }

        $message = __('messages.openwa_test_message', ['time' => now()->format('Y-m-d H:i')]);

        $sent = $openWa->send($phone, $message);

        return response()->json([
            'success' => $sent,
            'error' => $sent ? null : 'Gateway rejected the message. Check the session and try again.',
        ]);
    }
}