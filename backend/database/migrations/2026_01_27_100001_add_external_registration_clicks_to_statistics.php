<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('event_statistics', 'external_registration_clicks')) {
            Schema::table('event_statistics', function (Blueprint $table) {
                $table->unsignedInteger('external_registration_clicks')->default(0);
            });
        }

        if (!Schema::hasColumn('event_daily_statistics', 'external_registration_clicks')) {
            Schema::table('event_daily_statistics', function (Blueprint $table) {
                $table->unsignedInteger('external_registration_clicks')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_statistics', 'external_registration_clicks')) {
            Schema::table('event_statistics', function (Blueprint $table) {
                $table->dropColumn('external_registration_clicks');
            });
        }

        if (Schema::hasColumn('event_daily_statistics', 'external_registration_clicks')) {
            Schema::table('event_daily_statistics', function (Blueprint $table) {
                $table->dropColumn('external_registration_clicks');
            });
        }
    }
};
