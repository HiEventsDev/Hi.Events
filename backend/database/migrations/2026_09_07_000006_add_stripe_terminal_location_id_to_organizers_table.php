<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");

        Schema::table('organizers', static function (Blueprint $table) {
            $table->string('stripe_terminal_location_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizers', static function (Blueprint $table) {
            $table->dropColumn('stripe_terminal_location_id');
        });
    }
};
