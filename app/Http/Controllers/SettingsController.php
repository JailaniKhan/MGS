<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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
            'sms_sender' => Setting::get('sms_sender', config('services.sms.sender', '')),
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
            'sms_sender' => 'nullable|string|max:11',
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
}