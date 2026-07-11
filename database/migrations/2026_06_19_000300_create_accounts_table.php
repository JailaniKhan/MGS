<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->enum('type', ['customer', 'supplier', 'bank', 'cash', 'income', 'expense']);
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('currency', 3)->default('AFN');
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->string('legacy_type')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['legacy_type', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
