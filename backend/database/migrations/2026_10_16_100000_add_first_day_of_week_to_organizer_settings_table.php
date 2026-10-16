<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->smallInteger('first_day_of_week')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('first_day_of_week');
        });
    }
};
