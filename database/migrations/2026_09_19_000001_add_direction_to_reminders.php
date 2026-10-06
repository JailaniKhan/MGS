<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            // 'out' = messages we sent (the historical rows), 'in' = messages
            // received from the contact via the OpenWA gateway. Indexed so the
            // chat views can split threads cheaply.
            $table->string('direction', 10)->default('out')->after('status');
            $table->index(['remindable_type', 'remindable_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex(['remindable_type', 'remindable_id', 'direction']);
            $table->dropColumn('direction');
        });
    }
};
