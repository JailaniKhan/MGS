<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Accounting\ChartOfAccountsSeeder;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otps) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('messages.invalid_credentials'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request, ChartOfAccountsSeeder $chartOfAccounts)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        $chartOfAccounts->seedForUser($user);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function verifyPhoneOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
            'name' => 'nullable|string|max:255',
        ]);

        $cached = Cache::get("otp_{$validated['phone']}");

        if (! $cached || $cached !== (int) $validated['otp']) {
            return response()->json(['message' => __('messages.invalid_otp')], 401);
        }

        Cache::forget("otp_{$validated['phone']}");

        $user = User::where('phone', $validated['phone'])->first()
            ?? User::where('email', $validated['phone'].'@phone.local')->first();

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'] ?? $validated['phone'],
                'email' => $validated['phone'].'@phone.local',
                'phone' => $validated['phone'],
                'password' => bcrypt((string) random_int(100000000, 999999999)),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => route('dashboard'),
        ]);
    }
}
