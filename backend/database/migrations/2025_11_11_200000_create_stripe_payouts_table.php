<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_payouts', static function (Blueprint $table) {
            $table->id();
            $table->string('payout_id')->unique();
            $table->string('stripe_platform')->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->string('currency', 10)->nullable();
            $table->timestamp('payout_date')->nullable();
            $table->string('payout_status', 50)->nullable();
            $table->bigInteger('total_application_fee_vat_minor')->nullable();
            $table->bigInteger('total_application_fee_net_minor')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('reconciled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_payouts');
    }
};

