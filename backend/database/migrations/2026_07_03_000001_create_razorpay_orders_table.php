<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razorpay_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id');
            $table->string('razorpay_order_id')->unique();     // Razorpay order_xxx
            $table->string('razorpay_payment_id')->nullable(); // Razorpay pay_xxx (filled on success)
            $table->string('razorpay_signature')->nullable();  // HMAC signature from webhook/callback
            $table->integer('amount_minor');                   // Amount in minor units (e.g. paise for INR)
            $table->string('currency', 3);                     // ISO 4217 currency code (uppercase)
            $table->string('status')->default('created');      // created | paid | failed | refunded

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index('order_id');
            $table->index('razorpay_order_id');
            $table->index('razorpay_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_orders');
    }
};
