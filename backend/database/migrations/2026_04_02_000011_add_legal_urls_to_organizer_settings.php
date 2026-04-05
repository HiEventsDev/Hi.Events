<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->string('terms_of_service_url', 500)->nullable();
            $table->string('privacy_policy_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn(['terms_of_service_url', 'privacy_policy_url']);
        });
    }
};
