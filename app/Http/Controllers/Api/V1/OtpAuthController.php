<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpAuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $otp = random_int(100000, 999999);

        cache(["otp_{$validated['phone']}" => $otp], 300);

        $message = __('messages.otp_message', ['otp' => $otp]);
        try {
            if (! app(WhatsAppService::class)->send($validated['phone'], $message)) {
                logger("OTP for {$validated['phone']}: {$otp}");
            }
        } catch (\Throwable $e) {
            logger("OTP for {$validated['phone']}: {$otp}");
        }

        return response()->json([
            'message' => __('messages.otp_sent'),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
            'name' => 'nullable|string|max:255',
        ]);

        $cached = cache("otp_{$validated['phone']}");

        if (! $cached || $cached !== (int) $validated['otp']) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        cache()->forget("otp_{$validated['phone']}");

        // Prefer an account registered with this phone number; fall back to
        // the legacy phone-OTP convention (email = phone@phone.local).
        $user = User::where('phone', $validated['phone'])->first()
            ?? User::where('email', $validated['phone'].'@phone.local')->first();

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'] ?? 'User',
                'email' => $validated['phone'].'@phone.local',
                'phone' => $validated['phone'],
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        $token = $user->createToken('phone-auth')->plainTextToken;

        return response()->json([
            'message' => 'Authenticated successfully',
            'token' => $token,
            'user' => $user,
        ]);
    }
}
