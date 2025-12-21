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
        Schema::table('order_payment_platform_fees', static function (Blueprint $table) {
            $table->renameColumn('application_fee_amount', 'application_fee_gross_amount');
            $table->decimal('application_fee_net_amount', 10, 2)->nullable();
            $table->decimal('application_fee_vat_amount', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_payment_platform_fees', static function (Blueprint $table) {
            $table->dropColumn(['application_fee_net_amount', 'application_fee_vat_amount']);
            $table->renameColumn('application_fee_gross_amount', 'application_fee_amount');
        });
    }
};
