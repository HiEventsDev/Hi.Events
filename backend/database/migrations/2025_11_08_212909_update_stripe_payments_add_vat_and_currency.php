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
            $table->renameColumn('application_fee', 'application_fee_gross');
            $table->bigInteger('application_fee_net')->nullable();
            $table->bigInteger('application_fee_vat')->nullable();
            $table->string('currency', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_payments', static function (Blueprint $table) {
            $table->dropColumn(['application_fee_net', 'application_fee_vat', 'currency']);
            $table->renameColumn('application_fee_gross', 'application_fee');
        });
    }
};
