<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('event_statistics', 'orders_cancelled')) {
            Schema::table('event_statistics', function (Blueprint $table) {
                $table->unsignedInteger('orders_cancelled')->default(0);
            });
        }

        if (!Schema::hasColumn('event_daily_statistics', 'orders_cancelled')) {
            Schema::table('event_daily_statistics', function (Blueprint $table) {
                $table->unsignedInteger('orders_cancelled')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('event_statistics', 'orders_cancelled')) {
            Schema::table('event_statistics', function (Blueprint $table) {
                $table->dropColumn('orders_cancelled');
            });
        }

        if (Schema::hasColumn('event_daily_statistics', 'orders_cancelled')) {
            Schema::table('event_daily_statistics', function (Blueprint $table) {
                $table->dropColumn('orders_cancelled');
            });
        }
    }
};
