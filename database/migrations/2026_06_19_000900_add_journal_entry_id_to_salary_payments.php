<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payments') && ! Schema::hasColumn('salary_payments', 'journal_entry_id')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_payments') && Schema::hasColumn('salary_payments', 'journal_entry_id')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->dropColumn('journal_entry_id');
            });
        }
    }
};
