<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('allow_ticket_resale')->default(false);
            $table->unsignedInteger('ticket_resale_cutoff_minutes')->default(120);
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->boolean('default_allow_ticket_resale')->default(false);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_resellable')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_resellable');
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('default_allow_ticket_resale');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['allow_ticket_resale', 'ticket_resale_cutoff_minutes']);
        });
    }
};
