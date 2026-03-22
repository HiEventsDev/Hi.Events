<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('show_data_collection_disclaimer')->default(true);
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->boolean('default_show_data_collection_disclaimer')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('show_data_collection_disclaimer');
        });

        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('default_show_data_collection_disclaimer');
        });
    }
};
