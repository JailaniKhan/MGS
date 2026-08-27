<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings were shop-global while every other entity is per-user, so one
     * account's PIN hash / company details leaked to all others. Give each
     * row an owner and scope reads through the model.
     *
     * Existing rows were created by the single real operator of a
     * device-local install — hand them to the first account. The
     * `double_entry_migrated` machine flag stays userless on purpose: it is
     * written and read by artisan commands, which run unauthenticated.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id');
            $table->unique(['user_id', 'key']);
        });

        // The old global unique(key) index must go once ownership lands —
        // two users may legitimately hold the same key now. SQLite names it
        // "settings_key_unique" from the original column-level unique().
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_key_unique');
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('settings')
                ->whereNull('user_id')
                ->where('key', '!=', 'double_entry_migrated')
                ->update(['user_id' => $firstUserId]);
        }
    }

    public function down(): void
    {
        $keepUserId = DB::table('users')->orderBy('id')->value('id');

        // Collapse to one global set without tripping the restored
        // unique(key): machine flags survive as-is, the oldest account's
        // values become them, every other account's rows are discarded.
        if ($keepUserId !== null) {
            DB::table('settings')
                ->whereNotNull('user_id')
                ->where('user_id', '!=', $keepUserId)
                ->delete();
            DB::table('settings')
                ->where('user_id', $keepUserId)
                ->update(['user_id' => null]);
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->unique('key');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
            $table->dropColumn('user_id');
        });
    }
};
