<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove bank accounts first so the foreign key on bank_account_details can cascade.
        DB::table('accounts')->where('type', 'bank')->delete();

        Schema::dropIfExists('bank_account_details');
    }

    public function down(): void
    {
        Schema::create('bank_account_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('branch')->nullable();
            $table->timestamps();
        });
    }
};
