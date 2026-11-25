<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_settings', function (Blueprint $table) {
            $table->boolean('new_event_notifications_enabled')->default(false);
            $table->integer('new_event_notifications_generation')->default(0);
        });
        Schema::table('events', fn (Blueprint $table) => $table->timestamp('first_published_at', 6)->nullable());
        DB::table('events')->whereIn('status', ['LIVE', 'ARCHIVED', 'PENDING_MANUAL_REVIEW'])
            ->update(['first_published_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('new_event_subscription_requested_at')->nullable();
            $table->timestamp('new_event_subscription_processed_at')->nullable();
        });
        Schema::create('organizer_event_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('locale', 12)->default('en');
            $table->string('source', 20);
            $table->timestamp('subscribed_at');
            $table->timestamp('confirmed_at', 6)->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('confirmation_token_hash', 64)->nullable()->unique();
            $table->timestamp('confirmation_expires_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['organizer_id', 'email'], 'organizer_event_subscriptions_email_unique');
            $table->index(['organizer_id', 'confirmed_at', 'id'], 'organizer_event_subscriptions_confirmed_index');
        });
        Schema::create('event_subscription_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('organizer_event_subscriptions')->cascadeOnDelete();
            $table->integer('generation');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_subscription_deliveries');
        Schema::dropIfExists('organizer_event_subscriptions');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['new_event_subscription_requested_at', 'new_event_subscription_processed_at']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('first_published_at'));
        Schema::table('organizer_settings', fn (Blueprint $table) => $table->dropColumn(['new_event_notifications_enabled', 'new_event_notifications_generation']));
    }
};
