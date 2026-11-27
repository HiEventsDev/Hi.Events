<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->boolean('kiosk_lookup_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->dropColumn('kiosk_lookup_enabled');
        });
    }
};
