<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function index()
    {
        $steps = [
            'company' => Setting::get('company_name') !== null && Setting::get('company_name') !== 'My Business',
            'currency' => Setting::get('currency') !== null,
            'categories' => Category::count() > 0,
            'units' => Unit::count() > 0,
        ];

        return view('onboarding.index', compact('steps'));
    }

    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_phone' => 'nullable|string|max:50',
            'company_address' => 'nullable|string|max:500',
            'currency' => 'required|in:AFN,USD',
        ]);

        Setting::set('company_name', $validated['company_name']);
        if (!empty($validated['company_phone'])) Setting::set('company_phone', $validated['company_phone']);
        if (!empty($validated['company_address'])) Setting::set('company_address', $validated['company_address']);
        Setting::set('currency', $validated['currency']);

        return response()->json(['success' => true, 'next' => 'categories']);
    }

    public function storeStep2(Request $request)
    {
        $validated = $request->validate([
            'categories' => 'nullable|string',
        ]);

        if (!empty($validated['categories'])) {
            $categories = array_map('trim', explode(',', $validated['categories']));
            foreach ($categories as $cat) {
                if (!empty($cat)) {
                    Category::firstOrCreate(['name' => $cat]);
                }
            }
        }

        return response()->json(['success' => true, 'next' => 'units']);
    }

    public function storeStep3(Request $request)
    {
        $validated = $request->validate([
            'units' => 'nullable|string',
        ]);

        if (!empty($validated['units'])) {
            $units = array_map('trim', explode(',', $validated['units']));
            foreach ($units as $unit) {
                if (!empty($unit)) {
                    $parts = explode(':', $unit);
                    $name = trim($parts[0]);
                    $short = isset($parts[1]) ? trim($parts[1]) : strtoupper(substr($name, 0, 3));
                    Unit::firstOrCreate(['name' => $name], ['short_name' => $short]);
                }
            }
        }

        Setting::set('onboarding_complete', '1');

        return response()->json(['success' => true, 'redirect' => route('dashboard')]);
    }
}
