<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->after('user_id')
                ->constrained('purchases')->nullOnDelete();
            $table->index(['purchase_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['purchase_id', 'currency']);
            $table->dropConstrainedForeignId('purchase_id');
        });
    }
};
