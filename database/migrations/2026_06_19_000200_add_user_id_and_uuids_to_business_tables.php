<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'customers',
        'suppliers',
        'categories',
        'units',
        'products',
        'orders',
        'purchases',
        'employees',
        'cashbook_entries',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'user_id')) {
                    $blueprint->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn($table, 'uuid')) {
                    $blueprint->uuid('uuid')->nullable()->after('user_id');
                }
            });
        }

        foreach (['orders', 'purchases', 'employees'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'journal_entry_id')) {
                    $blueprint->unsignedBigInteger('journal_entry_id')->nullable()->after('uuid');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['orders', 'purchases', 'employees'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'journal_entry_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('journal_entry_id');
                });
            }
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'uuid')) {
                    $blueprint->dropColumn('uuid');
                }
                if (Schema::hasColumn($table, 'user_id')) {
                    $blueprint->dropForeign(['user_id']);
                    $blueprint->dropColumn('user_id');
                }
            });
        }
    }
};
