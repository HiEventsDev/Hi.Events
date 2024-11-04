<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('event_statistics', 'attendees_registered')) {
            Schema::table('event_statistics', static function (Blueprint $table) {
                $table->unsignedInteger('attendees_registered')->default(0);
            });
        }

        if (!Schema::hasColumn('event_daily_statistics', 'attendees_registered')) {
            Schema::table('event_daily_statistics', static function (Blueprint $table) {
                $table->unsignedInteger('attendees_registered')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_statistics', 'attendees_registered')) {
            Schema::table('event_statistics', static function (Blueprint $table) {
                $table->dropColumn('attendees_registered');
            });
        }

        if (Schema::hasColumn('event_daily_statistics', 'attendees_registered')) {
            Schema::table('event_daily_statistics', static function (Blueprint $table) {
                $table->dropColumn('attendees_registered');
            });
        }
    }
};
