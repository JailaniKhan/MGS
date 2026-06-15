<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::withCount('products')->orderBy('name')->get();
        return view('units.index', compact('units'));
    }

    public function create()
    {
        return view('units.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
        ]);

        Unit::create($validated);

        return redirect()->route('units.index')->with('success', 'واحد په بریالیتوب سره اضافه شو!');
    }

    public function edit(Unit $unit)
    {
        return view('units.edit', compact('unit'));
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
        ]);

        $unit->update($validated);

        return redirect()->route('units.index')->with('success', 'واحد په بریالیتوب سره سم شو!');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->count() > 0) {
            return redirect()->route('units.index')->with('error', 'دې واحد سره محصولات تړلي دي، لومړی هغه ړنګ کړئ!');
        }
        $unit->delete();
        return redirect()->route('units.index')->with('success', 'واحد په بریالیتوب سره ړنګ شو!');
    }
}