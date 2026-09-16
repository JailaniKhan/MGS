<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * BillService::send() used to store the fully-qualified class name
     * (App\Models\Customer) in remindable_type instead of the morph map
     * alias (customer). Those rows produced /whatsapp-chats/App%5CModels...
     * links that 404'd and never matched the conversation query. Fold
     * them back onto the aliases every other writer uses.
     */
    public function up(): void
    {
        DB::table('reminders')->where('remindable_type', 'App\\Models\\Customer')->update(['remindable_type' => 'customer']);
        DB::table('reminders')->where('remindable_type', 'App\\Models\\Supplier')->update(['remindable_type' => 'supplier']);
    }

    public function down(): void
    {
        DB::table('reminders')->where('remindable_type', 'customer')->update(['remindable_type' => 'App\\Models\\Customer']);
        DB::table('reminders')->where('remindable_type', 'supplier')->update(['remindable_type' => 'App\\Models\\Supplier']);
    }
};
