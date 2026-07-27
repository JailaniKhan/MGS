<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'unit')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        return view('products.create', compact('categories', 'units'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', __('messages.product_created'));
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        return view('products.edit', compact('product', 'categories', 'units'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255|unique:products,barcode,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $oldStock = $product->stock;
        $product->update($validated);
        $newStock = $product->fresh()->stock;

        if ($oldStock !== $newStock) {
            $change = $newStock - $oldStock;
            StockMovement::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'quantity_change' => $change,
                'movement_type' => 'adjustment',
                'reference_type' => 'product',
                'reference_id' => $product->id,
                'notes' => __('messages.stock_adjusted'),
            ]);
        }

        return redirect()->route('products.index')->with('success', __('messages.product_updated'));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', __('messages.product_deleted'));
    }
}