<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'unit')->orderBy('name')->get();
        $categories = Category::withCount('products')->orderBy('name')->get();
        $units = Unit::withCount('products')->orderBy('name')->get();
        return view('inventory.index', compact('products', 'categories', 'units'));
    }
}