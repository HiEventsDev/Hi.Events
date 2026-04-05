<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_order_recoveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('email');
            $table->string('recovery_token', 64)->unique();
            $table->integer('emails_sent')->default(0);
            $table->timestamp('last_email_sent_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->string('promo_code')->nullable();
            $table->decimal('cart_total', 14, 2)->default(0);
            $table->json('cart_items')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('abandoned_checkout_recovery_enabled')->default(false);
            $table->integer('abandoned_checkout_delay_minutes')->default(60);
            $table->integer('abandoned_checkout_max_emails')->default(2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_order_recoveries');

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn([
                'abandoned_checkout_recovery_enabled',
                'abandoned_checkout_delay_minutes',
                'abandoned_checkout_max_emails',
            ]);
        });
    }
};
