<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name')->nullable(); // e.g., "Main Entrance", "VIP Booth"
            $table->string('status')->default('active'); // active, closed
            $table->string('device_name')->nullable();
            $table->string('stripe_location_id')->nullable();
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_cash', 14, 2)->default(0);
            $table->decimal('total_card', 14, 2)->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_session_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('payment_method')->default('card'); // card, cash, free
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('status')->default('completed'); // completed, refunded, failed
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('pos_session_id')->references('id')->on('pos_sessions')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_transactions');
        Schema::dropIfExists('pos_sessions');
    }
};
