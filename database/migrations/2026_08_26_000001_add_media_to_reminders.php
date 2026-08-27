<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            // Voice notes (and any future media) sent with a WhatsApp
            // message. media_path is relative to the local storage disk so
            // the chat view can stream it back for playback.
            $table->string('media_path')->nullable()->after('message');
            $table->string('media_type')->nullable()->after('media_path');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_type']);
        });
    }
};
