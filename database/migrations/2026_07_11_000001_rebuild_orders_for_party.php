<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Build the new orders table with a nullable customer_id so an order can
        //    also be linked to a supplier via person_type / person_id.
        Schema::create('orders_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
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

        // 2. Copy existing orders, treating them as customers.
        $orders = DB::table('orders')->get();
        foreach ($orders as $order) {
            DB::table('orders_new')->insert([
                'id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'currency' => $order->currency ?? 'AFN',
                'tax_rate' => $order->tax_rate ?? 0,
                'tax_amount' => $order->tax_amount ?? 0,
                'subtotal' => $order->subtotal ?? 0,
                'tax_type' => $order->tax_type ?? 'exclusive',
                'person_type' => 'customer',
                'person_id' => $order->customer_id,
            ]);
        }

        // 3. Keep the autoincrement sequence in sync for SQLite.
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement("UPDATE sqlite_sequence SET seq = (SELECT MAX(id) FROM orders_new) WHERE name = 'orders_new'");
        }

        // 4. Drop the dependent foreign keys, swap the tables, then recreate them.
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::drop('orders');
        Schema::rename('orders_new', 'orders');

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
        Schema::table('order_returns', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse: move supplier-linked rows back out, then restore the original table.
        Schema::create('orders_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->string('currency', 3)->default('AFN')->after('status');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('currency');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            $table->decimal('subtotal', 10, 2)->default(0)->after('tax_amount');
            $table->string('tax_type', 10)->default('exclusive')->after('tax_amount');
        });

        $orders = DB::table('orders')->where('person_type', 'customer')->get();
        foreach ($orders as $order) {
            DB::table('orders_old')->insert([
                'id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'currency' => $order->currency ?? 'AFN',
                'tax_rate' => $order->tax_rate ?? 0,
                'tax_amount' => $order->tax_amount ?? 0,
                'subtotal' => $order->subtotal ?? 0,
                'tax_type' => $order->tax_type ?? 'exclusive',
            ]);
        }

        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement("UPDATE sqlite_sequence SET seq = (SELECT MAX(id) FROM orders_old) WHERE name = 'orders_old'");
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::drop('orders');
        Schema::rename('orders_old', 'orders');

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
        Schema::table('order_returns', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
