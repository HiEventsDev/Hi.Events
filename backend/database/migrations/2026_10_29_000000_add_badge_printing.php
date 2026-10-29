<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('badge_design_settings')->nullable();
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->timestamp('badge_printed_at')->nullable();
            $table->unsignedInteger('badge_print_count')->default(0);
        });

        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->boolean('badge_printing_enabled')->default(false);
            $table->boolean('kiosk_mode')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('badge_design_settings');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn(['badge_printed_at', 'badge_print_count']);
        });

        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->dropColumn(['badge_printing_enabled', 'kiosk_mode']);
        });
    }
};
