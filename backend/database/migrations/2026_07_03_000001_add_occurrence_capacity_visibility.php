<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('show_available_occurrence_capacity')->default(false);
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->boolean('show_available_capacity')->nullable()->default(null);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('show_available_occurrence_capacity');
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropColumn('show_available_capacity');
        });
    }
};
