<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->string('checkout_validation_webhook_url', 2048)->nullable()->after('order_max_tickets');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('checkout_validation_webhook_url');
        });
    }
};
