<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Product extends Model
{
    use BelongsToUser;

    protected $fillable = ['name', 'barcode', 'lot_number', 'category_id', 'unit_id', 'price', 'price_usd', 'stock', 'stock_afn', 'stock_usd', 'description'];

    protected static function boot(): void
    {
        parent::boot();

        // Single source of pool normalization:
        // 1. A create that only sets legacy `stock` lands it in the AFN pool
        //    (the historical assumption); explicit pools always win.
        // 2. The combined column must always equal the pools; omitted pools
        //    fall back to their stored values. moveStock() keeps this in step
        //    for pool-only movements (increment() bypasses model events).
        static::saving(function (Product $product) {
            if (! $product->exists
                && $product->stock_afn === null
                && $product->stock_usd === null
                && $product->stock !== null) {
                $product->stock_afn = (int) $product->stock;
                $product->stock_usd = 0;
            }

            $product->stock_afn = (int) ($product->stock_afn ?? $product->getOriginal('stock_afn') ?? 0);
            $product->stock_usd = (int) ($product->stock_usd ?? $product->getOriginal('stock_usd') ?? 0);
            $product->stock = $product->stock_afn + $product->stock_usd;
        });
    }

    /**
     * Units on hand in a currency pool. Goods bought with USD cash and goods
     * bought with AFN cash are tracked as separate physical pools.
     */
    public function stockFor(string $currency): int
    {
        return $currency === 'USD' ? (int) $this->stock_usd : (int) $this->stock_afn;
    }

    public function hasStockFor(string $currency, int $quantity): bool
    {
        return $this->stockFor($currency) >= $quantity;
    }

    /**
     * Move units between the shelf and a currency pool. Callers must lock the
     * product row (lockForUpdate) and check hasStockFor() before negative moves.
     * The legacy `stock` column is kept as the combined total for queries that
     * still aggregate across pools.
     */
    public function moveStock(string $currency, int $delta): void
    {
        $pool = $currency === 'USD' ? 'stock_usd' : 'stock_afn';
        $this->increment($pool, $delta);
        $this->increment('stock', $delta);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * The lot currently on the shelf per currency pool: each product's most
     * recent non-cancelled purchase line carrying a usable lot. Legacy backfill
     * rows (the synthetic "OPENING" marker from opening-balance seeding) and
     * blank lots never surface — a product with no real purchase lot falls back
     * to its own master lot_number at the call site.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<string, string> keyed "productId:CURRENCY"
     */
    public static function latestPurchaseLots(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $rows = \DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->whereIn('purchase_items.product_id', $productIds)
            ->where('purchases.status', '!=', 'cancelled')
            ->whereNotNull('purchase_items.lot_number')
            ->where('purchase_items.lot_number', '<>', '')
            ->where('purchase_items.lot_number', '<>', 'OPENING')
            ->orderBy('purchases.created_at')
            ->orderBy('purchase_items.id')
            ->get([
                'purchase_items.product_id',
                'purchases.currency',
                'purchase_items.lot_number',
            ]);

        // Ascending order + overwrite = the latest line wins per pool.
        return $rows->mapWithKeys(fn ($row) => [
            $row->product_id.':'.$row->currency => (string) $row->lot_number,
        ]);
    }
}
