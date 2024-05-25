<?php

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->string('homepage_body_background_color', 100)->nullable();
            $table->string('homepage_background_type', 30)->default(HomepageBackgroundType::COLOR->name);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('homepage_body_background_color');
            $table->dropColumn('homepage_background_type');
        });
    }
};
