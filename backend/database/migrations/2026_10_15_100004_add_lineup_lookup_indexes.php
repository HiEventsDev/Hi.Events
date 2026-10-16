<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_lineup_entries', function (Blueprint $table) {
            $table->index('profile_id');
        });

        Schema::table('event_schedule_items', function (Blueprint $table) {
            $table->index('event_space_id');
        });

        Schema::table('event_spaces', function (Blueprint $table) {
            $table->index('event_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_spaces', function (Blueprint $table) {
            $table->dropIndex(['event_location_id']);
        });

        Schema::table('event_schedule_items', function (Blueprint $table) {
            $table->dropIndex(['event_space_id']);
        });

        Schema::table('event_lineup_entries', function (Blueprint $table) {
            $table->dropIndex(['profile_id']);
        });
    }
};
