<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('opted_into_marketing_at')->nullable()->after('notes');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('show_marketing_opt_in')->default(true)->after('ticket_design_settings');
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->boolean('default_show_marketing_opt_in')->default(true)->after('default_attendee_details_collection_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('opted_into_marketing_at');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('show_marketing_opt_in');
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('default_show_marketing_opt_in');
        });
    }
};
