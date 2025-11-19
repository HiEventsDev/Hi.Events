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
        Schema::table('order_payment_platform_fees', function (Blueprint $table) {
            $table->string('charge_id')->nullable()->after('transaction_id');
            $table->index('charge_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_payment_platform_fees', function (Blueprint $table) {
            $table->dropIndex(['charge_id']);
            $table->dropColumn('charge_id');
        });
    }
};
