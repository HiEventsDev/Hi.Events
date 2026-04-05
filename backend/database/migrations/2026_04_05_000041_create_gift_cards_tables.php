<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->string('code', 32)->unique();
            $table->decimal('original_amount', 14, 2);
            $table->decimal('balance', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('active'); // active, depleted, expired, disabled
            $table->string('purchaser_name')->nullable();
            $table->string('purchaser_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->text('personal_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });

        Schema::create('gift_card_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gift_card_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('gift_card_id')->references('id')->on('gift_cards')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_usages');
        Schema::dropIfExists('gift_cards');
    }
};
