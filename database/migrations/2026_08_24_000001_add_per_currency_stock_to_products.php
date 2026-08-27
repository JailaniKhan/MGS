<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_afn')->default(0)->after('stock');
            $table->integer('stock_usd')->default(0)->after('stock_afn');
        });

        // Legacy stock was currency-agnostic; carry it into the AFN pool as the
        // default assumption. Owners can rebalance via the product edit form.
        DB::table('products')->update(['stock_afn' => DB::raw('stock')]);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('quantity_change');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_afn', 'stock_usd']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
