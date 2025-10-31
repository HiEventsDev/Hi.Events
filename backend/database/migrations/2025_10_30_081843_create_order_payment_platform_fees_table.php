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
        Schema::create('order_payment_platform_fees', static function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');
            $table->string('payment_platform', 50);
            $table->jsonb('fee_rollup')->nullable();
            $table->decimal('payment_platform_fee_amount', 10, 2);
            $table->decimal('application_fee_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_payment_platform_fees');
    }
};
