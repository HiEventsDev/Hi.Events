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
        Schema::table('stripe_payments', function (Blueprint $table) {
            $table->bigInteger('payout_stripe_fee')->nullable()->after('payout_id');
            $table->bigInteger('payout_net_amount')->nullable()->after('payout_stripe_fee');
            $table->string('payout_currency', 10)->nullable()->after('payout_net_amount');
            $table->decimal('payout_exchange_rate', 20, 10)->nullable()->after('payout_currency');
            $table->string('balance_transaction_id')->nullable()->after('payout_exchange_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_payments', function (Blueprint $table) {
            $table->dropColumn([
                'balance_transaction_id',
                'payout_exchange_rate',
                'payout_currency',
                'payout_net_amount',
                'payout_stripe_fee',
            ]);
        });
    }
};
