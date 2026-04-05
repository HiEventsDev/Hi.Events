<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_statistics', function (Blueprint $table) {
            $table->integer('orders_abandoned')->default(0)->after('orders_cancelled');
        });

        Schema::table('event_daily_statistics', function (Blueprint $table) {
            $table->integer('orders_abandoned')->default(0)->after('orders_cancelled');
        });
    }

    public function down(): void
    {
        Schema::table('event_statistics', function (Blueprint $table) {
            $table->dropColumn('orders_abandoned');
        });

        Schema::table('event_daily_statistics', function (Blueprint $table) {
            $table->dropColumn('orders_abandoned');
        });
    }
};
