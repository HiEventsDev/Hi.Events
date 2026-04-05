<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->string('event_location_type', 20)->default('IN_PERSON')->after('is_online_event');
        });

        // Backfill from is_online_event boolean
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE event_settings SET event_location_type = CASE WHEN is_online_event = true THEN 'ONLINE' ELSE 'IN_PERSON' END"
        );
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('event_location_type');
        });
    }
};
