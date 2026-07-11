<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cashbook_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['in', 'out']); // 'in' = cash received, 'out' = cash spent
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('AFN'); // AFN or USD
            $table->string('category')->nullable(); // e.g. sale, expense, salary, other
            $table->text('notes')->nullable();
            $table->date('entry_date');
            $table->timestamps();

            $table->index(['currency', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashbook_entries');
    }
};
