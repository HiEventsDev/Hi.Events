<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_resale_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->foreignId('product_price_id')->constrained('product_prices')->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->string('status', 20);
            $table->decimal('min_unit_price', 14, 2);
            $table->decimal('estimated_refund', 14, 2);
            $table->string('connected_account_id')->nullable();
            $table->foreignId('claimed_by_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('withdrawal_requested_at')->nullable();
            $table->foreignId('sold_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('refund_amount', 14, 2)->nullable();
            $table->string('refund_status', 20)->nullable();
            $table->unsignedSmallInteger('refund_attempts')->default(0);
            $table->string('stripe_refund_id')->nullable()->index();
            $table->string('closed_reason', 40)->nullable();
            $table->timestamps();

            $table->index(['product_price_id', 'event_occurrence_id', 'status']);
            $table->index(['event_id', 'status']);
            $table->index('claimed_by_order_id');
        });

        DB::statement("CREATE UNIQUE INDEX ticket_resale_listings_one_listed_per_attendee ON ticket_resale_listings (attendee_id) WHERE status = 'LISTED'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_resale_listings');
    }
};
