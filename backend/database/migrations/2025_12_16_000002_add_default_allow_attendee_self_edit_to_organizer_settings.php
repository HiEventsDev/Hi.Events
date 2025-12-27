<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->boolean('default_allow_attendee_self_edit')->default(true)->after('default_pass_platform_fee_to_buyer');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('default_allow_attendee_self_edit');
        });
    }
};
