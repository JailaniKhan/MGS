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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('person_type'); // 'customer' or 'supplier'
            $table->unsignedBigInteger('person_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3); // AFN or USD
            $table->string('type'); // 'payment_received' or 'payment_made'
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_type', 'person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};