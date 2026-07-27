<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('hide_sold_out_occurrences')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('hide_sold_out_occurrences');
        });
    }
};
