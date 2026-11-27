<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('allow_ticket_transfer')->default(true);
            $table->unsignedInteger('ticket_transfer_cutoff_minutes')->default(0);
            $table->unsignedInteger('max_transfers_per_ticket')->nullable();
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->boolean('default_allow_ticket_transfer')->default(true);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_transferable')->default(true);
        });

        DB::statement('UPDATE event_settings SET allow_ticket_transfer = allow_attendee_self_edit');
        DB::statement('UPDATE organizer_settings SET default_allow_ticket_transfer = default_allow_attendee_self_edit');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_transferable');
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('default_allow_ticket_transfer');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['allow_ticket_transfer', 'ticket_transfer_cutoff_minutes', 'max_transfers_per_ticket']);
        });
    }
};
