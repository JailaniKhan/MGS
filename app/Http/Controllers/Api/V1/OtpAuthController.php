<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        // In production, send via SMS gateway
        // For development, log to console
        logger("OTP for {$validated['phone']}: {$otp}");

        return response()->json([
            'message' => 'OTP sent successfully',
            'otp' => $otp,
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

        if (!$cached || $cached !== (int) $validated['otp']) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        cache()->forget("otp_{$validated['phone']}");

        $user = User::where('email', $validated['phone'] . '@phone.local')->first();

        if (!$user) {
            $user = User::create([
                'name' => $validated['name'] ?? 'User',
                'email' => $validated['phone'] . '@phone.local',
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
