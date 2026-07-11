<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('remindable_type');
            $table->unsignedBigInteger('remindable_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('AFN');
            $table->string('channel')->default('sms');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['remindable_type', 'remindable_id'], 'rem_remindable_idx');
            $table->index('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('notes');
        });

        Schema::table('party_payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('party_payments', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
        Schema::dropIfExists('reminders');
    }
};
