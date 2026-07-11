<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('expense_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('AFN');
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expense_date']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};