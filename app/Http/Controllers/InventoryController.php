<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;

class InventoryController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'unit')
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

        $threshold = (int) Setting::get('min_stock_threshold', 10);

        // Stock value is pool-aware: each pool multiplies by its own currency's
        // price, so an AFN-only product never inflates the USD tile.
        $totals = Product::get(['stock_afn', 'stock_usd', 'price', 'price_usd']);
        $totalValueAFN = $totals->reduce(
            fn ($carry, $p) => bcadd($carry, bcmul((string) (int) $p->stock_afn, (string) $p->price, 2), 2),
            '0.00'
        );
        $totalValueUSD = $totals->reduce(
            fn ($carry, $p) => bcadd($carry, bcmul((string) (int) $p->stock_usd, (string) ($p->price_usd ?? '0'), 2), 2),
            '0.00'
        );
        // A currency tile renders only when that POOL has stock: a USD price with
        // no USD units must not produce a misleading "0.00 USD" headline.
        $hasAFN = Product::where('stock_afn', '>', 0)->exists();
        $hasUSD = Product::where('stock_usd', '>', 0)->exists();
        $productCount = Product::count();

        // Low stock means a specific POOL ran low, not the combined total:
        // 2 AFN + 500 USD must not hide the AFN shortage.
        $lowStockCount = $threshold > 0
            ? Product::where(function ($q) use ($threshold) {
                $q->where(fn ($afn) => $afn->where('stock_afn', '>', 0)->where('stock_afn', '<', $threshold))
                    ->orWhere(fn ($usd) => $usd->where('stock_usd', '>', 0)->where('stock_usd', '<', $threshold));
            })->count()
            : 0;

        // The lot on the shelf comes from the latest real purchase per pool,
        // not from the master field (which can lag behind actual batches).
        $latestLots = Product::latestPurchaseLots($products->getCollection()->pluck('id')->all());

        return view('inventory.index', compact(
            'products', 'categories', 'units',
            'productCount', 'totalValueAFN', 'totalValueUSD', 'hasAFN', 'hasUSD', 'lowStockCount', 'threshold', 'latestLots'
        ));
    }
}
