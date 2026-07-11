<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ledger_entries') && ! Schema::hasTable('party_payments')) {
            Schema::rename('ledger_entries', 'party_payments');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('party_payments') && ! Schema::hasTable('ledger_entries')) {
            Schema::rename('party_payments', 'ledger_entries');
        }
    }
};
