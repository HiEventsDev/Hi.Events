<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->jsonb('tracking_pixels')->nullable();
            $table->boolean('tracking_consent_acknowledged')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->dropColumn('tracking_pixels');
            $table->dropColumn('tracking_consent_acknowledged');
        });
    }
};
