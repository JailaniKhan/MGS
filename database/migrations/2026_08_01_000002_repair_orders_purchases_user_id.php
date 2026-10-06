<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration.
 *
 * The earlier 2026_07_11_000001 / 2026_07_11_000002 rebuild migrations were
 * written before user_id / uuid / journal_entry_id were understood and
 * forgot to carry them over, so any environment where they already ran is
 * missing those columns on orders / purchases. Restore them and backfill
 * user_id=1 (system owner) so the BelongsToUser global scope can see rows.
 *
 * Idempotent — safe to run on a fresh install where the columns already
 * exist (we guard with hasColumn checks).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tables that need user_id + uuid backfill. Some (orders/purchases) also
        // had a journal_entry_id added by 2026_06_19_000200 that the rebuild
        // dropped; restore it here too.
        $tables = [
            'orders' => ['user_id', 'uuid', 'journal_entry_id'],
            'purchases' => ['user_id', 'uuid', 'journal_entry_id'],
            'payments' => ['user_id', 'uuid'],
            'units' => ['user_id', 'uuid'],
            'cashbook_entries' => ['user_id', 'uuid'],
        ];

        foreach ($tables as $table => $wantedColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $wantedColumns) {
                if (in_array('user_id', $wantedColumns) && ! Schema::hasColumn($table, 'user_id')) {
                    $blueprint->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
                }
                if (in_array('uuid', $wantedColumns) && ! Schema::hasColumn($table, 'uuid')) {
                    $blueprint->uuid('uuid')->nullable()->after('user_id');
                }
                if (in_array('journal_entry_id', $wantedColumns) && ! Schema::hasColumn($table, 'journal_entry_id')) {
                    $blueprint->unsignedBigInteger('journal_entry_id')->nullable()->after('uuid');
                }
            });

            // Backfill user_id where missing. Use the system user (id=1).
            if (Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => 1]);
            }
        }
    }

    public function down(): void
    {
        // No down path — we never want to drop these columns once restored.
    }
};
