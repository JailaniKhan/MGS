<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('currency');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            $table->decimal('subtotal', 10, 2)->default(0)->after('tax_amount');
            $table->string('tax_type', 10)->default('exclusive')->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount', 'subtotal', 'tax_type']);
        });
    }
};