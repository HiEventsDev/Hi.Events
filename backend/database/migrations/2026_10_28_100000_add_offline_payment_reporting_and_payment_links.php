<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('offline_payment_reporting_enabled')->default(true);
            $table->boolean('offline_payment_order_reference_enabled')->default(true);
            $table->boolean('payment_links_enabled')->default(false);
            $table->unsignedInteger('payment_link_expiry_hours')->default(72);
        });

        DB::table('event_settings')
            ->whereNotNull('offline_payment_instructions')
            ->where('offline_payment_instructions', '!=', '')
            ->where('offline_payment_instructions', 'not like', '%order.number%')
            ->update(['offline_payment_order_reference_enabled' => false]);

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_link_created_at')->nullable();
            $table->timestamp('offline_payment_reported_at')->nullable();
            $table->string('offline_payment_note', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn([
                'offline_payment_reporting_enabled',
                'offline_payment_order_reference_enabled',
                'payment_links_enabled',
                'payment_link_expiry_hours',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_link_created_at', 'offline_payment_reported_at', 'offline_payment_note']);
        });
    }
};
