<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_interval')->default('yearly'); // monthly, quarterly, yearly, lifetime
            $table->json('benefits')->nullable(); // JSON array of benefit descriptions
            $table->integer('max_events')->nullable(); // null = unlimited
            $table->integer('discount_percentage')->default(0); // 0-100 discount on ticket purchases
            $table->boolean('includes_priority_booking')->default(false);
            $table->string('status')->default('active'); // active, inactive, archived
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_plan_id')->index();
            $table->unsignedBigInteger('account_id')->index();
            $table->string('member_email')->index();
            $table->string('member_name');
            $table->string('membership_number', 32)->unique();
            $table->string('status')->default('active'); // active, expired, cancelled, suspended
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->integer('events_used')->default(0);
            $table->string('stripe_subscription_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('membership_plan_id')->references('id')->on('membership_plans')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });

        Schema::create('membership_event_access', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->unique(['membership_id', 'event_id']);
            $table->foreign('membership_id')->references('id')->on('memberships')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_event_access');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
    }
};
