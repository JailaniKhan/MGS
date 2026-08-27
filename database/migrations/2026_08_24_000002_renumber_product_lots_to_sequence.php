<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Lots are plain sequential numbers per owner (1, 2, 3...). Renumber
        // every existing lot — legacy "LOT-…" strings and date-stamped
        // numbers alike — into a clean sequence ordered by product id.
        $products = DB::table('products')
            ->whereNotNull('lot_number')
            ->where('lot_number', '<>', '')
            ->orderBy('id')
            ->get(['id', 'user_id']);

        $nextByOwner = [];
        foreach ($products as $product) {
            $owner = $product->user_id ?? 0;
            $nextByOwner[$owner] = ($nextByOwner[$owner] ?? 0) + 1;

            DB::table('products')
                ->where('id', $product->id)
                ->update(['lot_number' => (string) $nextByOwner[$owner]]);
        }
    }

    public function down(): void
    {
        // One-way data cleanup; the old LOT- values are not recoverable.
    }
};
