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
        $products = Product::with('category', 'unit', 'purchaseItems')
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'products_page')
            ->withQueryString();

        $categories = Category::withCount('products')
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'categories_page')
            ->withQueryString();

        $units = Unit::withCount('products')
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'units_page')
            ->withQueryString();

        return view('inventory.index', compact('products', 'categories', 'units'));
    }
}