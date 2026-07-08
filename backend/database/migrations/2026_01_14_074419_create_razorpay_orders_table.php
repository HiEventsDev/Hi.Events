<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('razorpay_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_signature')->nullable();

            $table->string('method')->nullable();
            $table->integer('fee')->nullable()->comment('Fee in paise');
            $table->integer('tax')->nullable()->comment('Tax in paise');

            $table->integer('amount');
            $table->string('currency', 3);
            $table->string('receipt')->nullable();

            $table->string('status')->default('created');

            $table->string('failure_reason')->nullable()->after('tax');
            $table->string('error_code')->nullable()->after('failure_reason');

            $table->timestamps();

            $table->index(['order_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('razorpay_orders');
    }
};
