<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->boolean('is_external_registration')->default(false);
            $table->string('external_registration_url', 500)->nullable();
            $table->string('external_registration_button_text', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn([
                'is_external_registration',
                'external_registration_url',
                'external_registration_button_text',
            ]);
        });
    }
};
