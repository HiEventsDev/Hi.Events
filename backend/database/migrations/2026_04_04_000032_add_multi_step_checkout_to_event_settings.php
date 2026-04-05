<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('multi_step_checkout_enabled')->default(false)->comment('Enable multi-step checkout wizard');
            $table->json('checkout_steps_config')->nullable()->comment('Step ordering and grouping config');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['multi_step_checkout_enabled', 'checkout_steps_config']);
        });
    }
};
