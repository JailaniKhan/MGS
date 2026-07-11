<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AppLockController extends Controller
{
    public function index()
    {
        $pinEnabled = Setting::get('pin_lock_enabled', '0');
        $biometricEnabled = Setting::get('biometric_lock_enabled', '0');

        return view('app-lock.index', compact('pinEnabled', 'biometricEnabled'));
    }

    public function setPin(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|string|size:4|confirmed',
        ]);

        Setting::set('pin_lock_hash', Hash::make($validated['pin']));
        Setting::set('pin_lock_enabled', '1');

        return redirect()->route('app-lock.index')->with('success', __('messages.pin_set_success'));
    }

    public function removePin(Request $request)
    {
        Setting::set('pin_lock_enabled', '0');
        Setting::set('pin_lock_hash', null);

        return redirect()->route('app-lock.index')->with('success', __('messages.pin_removed'));
    }

    public function toggleBiometric(Request $request)
    {
        $current = Setting::get('biometric_lock_enabled', '0');
        Setting::set('biometric_lock_enabled', $current === '1' ? '0' : '1');

        return redirect()->route('app-lock.index')->with('success', __('messages.saved_successfully'));
    }

    public function verifyPin(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|string',
        ]);

        $hash = Setting::get('pin_lock_hash');
        if ($hash && Hash::check($validated['pin'], $hash)) {
            session(['pin_verified' => true]);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => __('messages.invalid_pin')]);
    }

    public function lockScreen()
    {
        session()->forget('pin_verified');
        return view('app-lock.lock');
    }
}
