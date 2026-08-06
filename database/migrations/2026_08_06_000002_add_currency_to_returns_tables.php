<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->string('currency', 3)->default('AFN')->after('total_amount');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('currency', 3)->default('AFN')->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
