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
        Schema::table('stripe_payments', static function (Blueprint $table) {
            $table->decimal('application_fee_vat_rate', 5, 4)->nullable();
        });

        Schema::table('order_payment_platform_fees', static function (Blueprint $table) {
            $table->decimal('application_fee_vat_rate', 5, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_payments', static function (Blueprint $table) {
            $table->dropColumn('application_fee_vat_rate');
        });

        Schema::table('order_payment_platform_fees', static function (Blueprint $table) {
            $table->dropColumn('application_fee_vat_rate');
        });
    }
};
