<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->boolean('default_pass_platform_fee_to_buyer')->default(false)->after('default_show_marketing_opt_in');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('pass_platform_fee_to_buyer')->default(false)->after('show_marketing_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('default_pass_platform_fee_to_buyer');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('pass_platform_fee_to_buyer');
        });
    }
};
