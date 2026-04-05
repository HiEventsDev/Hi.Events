<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->unsignedInteger('order_min_tickets')->nullable()->after('order_timeout_in_minutes');
            $table->unsignedInteger('order_max_tickets')->nullable()->after('order_min_tickets');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['order_min_tickets', 'order_max_tickets']);
        });
    }
};
