<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * purchases:backfill-opening-stock used to stamp the literal placeholder
     * 'OPENING' onto every generated line, which then showed up as the lot
     * number on purchase pages. Replace it with the product's current lot
     * (or NULL when the product has none).
     */
    public function up(): void
    {
        $items = DB::table('purchase_items')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->where('purchase_items.lot_number', 'OPENING')
            ->select('purchase_items.id', 'products.lot_number as product_lot')
            ->get();

        foreach ($items as $item) {
            $lot = trim((string) $item->product_lot);

            DB::table('purchase_items')
                ->where('id', $item->id)
                ->update(['lot_number' => $lot !== '' ? $item->product_lot : null]);
        }
    }

    public function down(): void
    {
        //
    }
};
