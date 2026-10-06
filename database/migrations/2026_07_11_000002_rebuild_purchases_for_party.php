<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // 1. Rebuild `purchases` so a purchase can belong to a customer OR a
        //    supplier via person_type / person_id (mirrors the orders table).
        //    supplier_id is kept (nullable) for backwards compatibility.
        // -----------------------------------------------------------------
        Schema::create('purchases_new', function (Blueprint $table) {
            $table->id();
            // Preserve user_id / uuid / journal_entry_id from 2026_06_19_000200.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->string('currency', 3)->default('AFN')->after('status');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('currency');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            $table->decimal('subtotal', 10, 2)->default(0)->after('tax_amount');
            $table->string('tax_type', 10)->default('exclusive')->after('tax_amount');
            $table->string('person_type')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
        });

        $purchases = DB::table('purchases')->get();
        foreach ($purchases as $purchase) {
            DB::table('purchases_new')->insert([
                'id' => $purchase->id,
                'user_id' => $purchase->user_id ?? 1,
                'uuid' => $purchase->uuid ?? null,
                'journal_entry_id' => $purchase->journal_entry_id ?? null,
                'supplier_id' => $purchase->supplier_id,
                'status' => $purchase->status,
                'total_amount' => $purchase->total_amount,
                'created_at' => $purchase->created_at,
                'updated_at' => $purchase->updated_at,
                'currency' => $purchase->currency ?? 'AFN',
                'tax_rate' => $purchase->tax_rate ?? 0,
                'tax_amount' => $purchase->tax_amount ?? 0,
                'subtotal' => $purchase->subtotal ?? 0,
                'tax_type' => $purchase->tax_type ?? 'exclusive',
                'person_type' => 'supplier',
                'person_id' => $purchase->supplier_id,
            ]);
        }

        if (DB::connection() instanceof SQLiteConnection) {
            DB::statement("UPDATE sqlite_sequence SET seq = (SELECT MAX(id) FROM purchases_new) WHERE name = 'purchases_new'");
        }

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });

        Schema::drop('purchases');
        Schema::rename('purchases_new', 'purchases');

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });

        // -----------------------------------------------------------------
        // 2. Rebuild `purchase_returns` so supplier_id can be NULL (a return
        //    may belong to a customer purchase, in which case we store NULL
        //    and resolve the party through the parent purchase).
        // -----------------------------------------------------------------
        Schema::create('purchase_returns_new', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('return_date');
            $table->string('reason')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $returns = DB::table('purchase_returns')->get();
        foreach ($returns as $return) {
            DB::table('purchase_returns_new')->insert([
                'id' => $return->id,
                'uuid' => $return->uuid,
                'user_id' => $return->user_id,
                'purchase_id' => $return->purchase_id,
                'supplier_id' => $return->supplier_id,
                'return_date' => $return->return_date,
                'reason' => $return->reason,
                'total_amount' => $return->total_amount,
                'status' => $return->status,
                'created_at' => $return->created_at,
                'updated_at' => $return->updated_at,
            ]);
        }

        if (DB::connection() instanceof SQLiteConnection) {
            DB::statement("UPDATE sqlite_sequence SET seq = (SELECT MAX(id) FROM purchase_returns_new) WHERE name = 'purchase_returns_new'");
        }

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_return_id']);
        });

        Schema::drop('purchase_returns');
        Schema::rename('purchase_returns_new', 'purchase_returns');

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse purchases: move supplier-linked rows back, drop person columns.
        Schema::create('purchases_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->string('currency', 3)->default('AFN')->after('status');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('currency');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            $table->decimal('subtotal', 10, 2)->default(0)->after('tax_amount');
            $table->string('tax_type', 10)->default('exclusive')->after('tax_amount');
        });

        $purchases = DB::table('purchases')->where('person_type', 'supplier')->get();
        foreach ($purchases as $purchase) {
            DB::table('purchases_old')->insert([
                'id' => $purchase->id,
                'supplier_id' => $purchase->person_id,
                'total_amount' => $purchase->total_amount,
                'created_at' => $purchase->created_at,
                'updated_at' => $purchase->updated_at,
                'currency' => $purchase->currency ?? 'AFN',
            ]);
        }

        if (DB::connection() instanceof SQLiteConnection) {
            DB::statement("UPDATE sqlite_sequence SET seq = (SELECT MAX(id) FROM purchases_old) WHERE name = 'purchases_old'");
        }

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });

        Schema::drop('purchases');
        Schema::rename('purchases_old', 'purchases');

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });

        // Reverse purchase_returns to NOT NULL supplier_id.
        Schema::create('purchase_returns_old', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->date('return_date');
            $table->string('reason')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $returns = DB::table('purchase_returns')->get();
        foreach ($returns as $return) {
            DB::table('purchase_returns_old')->insert([
                'id' => $return->id,
                'uuid' => $return->uuid,
                'user_id' => $return->user_id,
                'purchase_id' => $return->purchase_id,
                'supplier_id' => $return->supplier_id ?? 0,
                'return_date' => $return->return_date,
                'reason' => $return->reason,
                'total_amount' => $return->total_amount,
                'status' => $return->status,
                'created_at' => $return->created_at,
                'updated_at' => $return->updated_at,
            ]);
        }

        if (DB::connection() instanceof SQLiteConnection) {
            DB::statement("UPDATE sqlite_sequence SET seq = (SELECT MAX(id) FROM purchase_returns_old) WHERE name = 'purchase_returns_old'");
        }

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_return_id']);
        });

        Schema::drop('purchase_returns');
        Schema::rename('purchase_returns_old', 'purchase_returns');

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->cascadeOnDelete();
        });
    }
};
