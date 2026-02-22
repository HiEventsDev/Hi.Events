<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('waitlist_enabled')->default(false);
            $table->boolean('waitlist_auto_process')->default(false);
            $table->integer('waitlist_offer_timeout_minutes')->nullable()->default(null);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('waitlist_enabled')->nullable()->default(null);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn([
                'waitlist_enabled',
                'waitlist_auto_process',
                'waitlist_offer_timeout_minutes',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('waitlist_enabled');
        });
    }
};
