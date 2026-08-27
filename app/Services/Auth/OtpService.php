<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Phone-OTP issue/verify/provision — the single implementation behind both
 * the web login (`AuthController::verifyPhoneOtp`) and the API token flow
 * (`Api\V1\OtpAuthController`). Keeping it in one place stops the two
 * surfaces drifting apart again.
 */
class OtpService
{
    public function __construct(private WhatsAppService $whatsapp) {}

    /**
     * Generate, store and deliver a one-time code for the phone number.
     * Delivery failures are logged WITHOUT the code — log files are readable
     * by anyone with device access, which would defeat the OTP entirely.
     */
    public function send(string $phone): bool
    {
        $otp = random_int(100000, 999999);

        Cache::put($this->key($phone), $otp, now()->addMinutes(5));

        try {
            if ($this->whatsapp->send($phone, __('messages.otp_message', ['otp' => $otp]))) {
                return true;
            }
        } catch (\Throwable $e) {
            logger("OTP delivery failed for {$phone}: {$e->getMessage()}");

            return false;
        }

        logger("OTP delivery failed for {$phone}. The user must request a new code.");

        return false;
    }

    /**
     * Check a submitted code. A correct code is consumed immediately so it
     * can never be replayed; a wrong one leaves the real code untouched.
     */
    public function verify(string $phone, string $otp): bool
    {
        $cached = Cache::get($this->key($phone));

        if (! $cached || $cached !== (int) $otp) {
            return false;
        }

        Cache::forget($this->key($phone));

        return true;
    }

    /**
     * Find the account that owns this phone number, falling back to the
     * legacy phone-OTP convention (email = phone@phone.local), or provision
     * a fresh account when nobody holds the number yet.
     */
    public function resolveUser(string $phone, ?string $name = null): User
    {
        return User::where('phone', $phone)->first()
            ?? User::where('email', $phone.'@phone.local')->first()
            ?? User::create([
                'name' => $name ?: $phone,
                'email' => $phone.'@phone.local',
                'phone' => $phone,
                'password' => Hash::make(Str::random(32)),
            ]);
    }

    private function key(string $phone): string
    {
        return "otp_{$phone}";
    }
}
