<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class BusinessCardController extends Controller
{
    public function index()
    {
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
            'tax_id' => Setting::get('tax_id', ''),
        ];

        return view('business-card.index', compact('company'));
    }

    public function shareWhatsApp(Request $request)
    {
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
            'tax_id' => Setting::get('tax_id', ''),
        ];

        $message = "📇 *{$company['name']}*\n";
        if ($company['address']) $message .= "📍 {$company['address']}\n";
        if ($company['phone']) $message .= "📞 {$company['phone']}\n";
        if ($company['email']) $message .= "✉️ {$company['email']}\n";
        if ($company['tax_id']) $message .= "🆔 Tax ID: {$company['tax_id']}\n";
        $message .= "\n---\nShared via MGS App";

        $encoded = urlencode($message);
        $url = "https://wa.me/?text={$encoded}";

        return redirect($url);
    }
}
