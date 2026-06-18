<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->boolean('hide_branding')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->dropColumn('hide_branding');
        });
    }
};
