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
            'tax_id' => Setting::get('tax_id', ''),
            'default_tax_rate' => Setting::get('default_tax_rate', '0'),
            'default_tax_type' => Setting::get('default_tax_type', 'exclusive'),
            'invoice_prefix' => Setting::get('invoice_prefix', 'INV-'),
            'currency' => Setting::get('currency', 'AFN'),
            'language' => Setting::get('language', 'ps'),
            'whatsapp_api_key' => Setting::get('whatsapp_api_key', ''),
            'whatsapp_phone_number_id' => Setting::get('whatsapp_phone_number_id', ''),
            'anthropic_api_key' => Setting::get('anthropic_api_key', ''),
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
            'tax_id' => 'nullable|string|max:100',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'default_tax_type' => 'nullable|in:inclusive,exclusive',
            'invoice_prefix' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:3',
            'language' => 'nullable|string|in:en,ps,fa',
            'whatsapp_api_key' => 'nullable|string|max:255',
            'whatsapp_phone_number_id' => 'nullable|string|max:255',
            'anthropic_api_key' => 'nullable|string|max:255',
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
        $gatewayRunning = $configured && $manager->isRunning();
        $status = $configured && $gatewayRunning ? $openWa->sessionStatus() : null;
        $qr = null;

        if ($status && ($status['status'] ?? null) === 'qr_ready') {
            $qr = $openWa->qrCode();
        }

        return view('settings.openwa', compact('configured', 'gatewayRunning', 'status', 'qr'));
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

        $gatewayRunning = $manager->isRunning();
        $status = $gatewayRunning ? $openWa->sessionStatus() : null;
        $qr = null;

        if ($status && ($status['status'] ?? null) === 'qr_ready') {
            $qr = $openWa->qrCode();
        }

        return response()->json([
            'configured' => true,
            'gateway_running' => $gatewayRunning,
            'status' => $status['status'] ?? null,
            'qr' => $qr,
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

        $code = $openWa->requestPairingCode($validated['phone']);

        if (!$code) {
            return response()->json(['error' => 'Could not generate pairing code.'], 422);
        }

        return response()->json(['pairingCode' => $code]);
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