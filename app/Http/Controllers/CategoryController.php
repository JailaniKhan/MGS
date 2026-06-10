<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->orderBy('name')->get();
        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Category::create($validated);

        return redirect()->route('categories.index')->with('success', 'کتګوري په بریالیتوب سره اضافه شوه!');
    }

    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', 'کتګوري په بریالیتوب سره سمه شوه!');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0) {
            return redirect()->route('categories.index')->with('error', 'دې کتګورۍ سره محصولات تړلي دي، لومړی هغه ړنګ کړئ!');
        }
        $category->delete();
        return redirect()->route('categories.index')->with('success', 'کتګوري په بریالیتوب سره ړنګه شوه!');
    }
}