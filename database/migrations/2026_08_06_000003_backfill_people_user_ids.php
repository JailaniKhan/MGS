<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy people rows predate the BelongsToUser trait and carry a NULL
     * user_id, which makes the user scope hide them from every account.
     * Backfill from the user who owns the documents referencing them,
     * falling back to the first user when no reference exists.
     */
    public function up(): void
    {
        foreach (['customer' => 'customers', 'supplier' => 'suppliers'] as $type => $table) {
            foreach (DB::table($table)->whereNull('user_id')->pluck('id') as $id) {
                $owner = DB::table('orders')
                        ->where('person_type', $type)
                        ->where('person_id', $id)
                        ->whereNotNull('user_id')
                        ->orderBy('user_id')
                        ->value('user_id')
                    ?? DB::table('purchases')
                        ->where('person_type', $type)
                        ->where('person_id', $id)
                        ->whereNotNull('user_id')
                        ->orderBy('user_id')
                        ->value('user_id')
                    ?? DB::table('users')->orderBy('id')->value('id')
                    ?? 1;

                DB::table($table)->where('id', $id)->update(['user_id' => $owner]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
