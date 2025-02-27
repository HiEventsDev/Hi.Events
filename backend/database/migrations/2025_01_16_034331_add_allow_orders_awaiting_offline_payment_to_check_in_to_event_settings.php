<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->boolean('allow_orders_awaiting_offline_payment_to_check_in')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('allow_orders_awaiting_offline_payment_to_check_in');
        });
    }
};
