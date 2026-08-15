<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private const OTP_TTL_SECONDS = 300;

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendResetCode(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        if (! $this->findByPhone($validated['phone'])) {
            throw ValidationException::withMessages([
                'phone' => __('messages.phone_not_registered'),
            ]);
        }

        $otp = random_int(100000, 999999);

        cache(["otp_reset_{$validated['phone']}" => $otp], self::OTP_TTL_SECONDS);

        // The OTP channel is WhatsApp/SMS in production; for now it is logged
        // exactly like the phone-login OTP flow.
        logger("Password reset OTP for {$validated['phone']}: {$otp}");

        return redirect()->route('password.reset')->withInput(['phone' => $validated['phone']]);
    }

    public function showReset()
    {
        return view('auth.reset');
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = $this->findByPhone($validated['phone']);
        $cached = cache("otp_reset_{$validated['phone']}");

        if (! $user || ! $cached || $cached !== (int) $validated['otp']) {
            throw ValidationException::withMessages([
                'otp' => __('messages.invalid_otp'),
            ]);
        }

        cache()->forget("otp_reset_{$validated['phone']}");

        $user->update(['password' => $validated['password']]);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', __('messages.password_reset_success'));
    }

    private function findByPhone(string $phone): ?User
    {
        // Matches both accounts registered with a phone number and the
        // legacy phone-OTP convention (email = phone@phone.local).
        return User::query()
            ->where('phone', $phone)
            ->orWhere('email', $phone.'@phone.local')
            ->first();
    }
}
