<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");

        Schema::create('seat_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->string('seat_uid', 24);
            $table->boolean('is_zone')->default(false);
            $table->string('band_key', 24);
            $table->string('seat_label', 120);
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('attendees')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_price_id')->nullable()->constrained('product_prices')->nullOnDelete();
            $table->boolean('held_back')->default(false);
            $table->string('block_reason')->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->index('event_occurrence_id');
            $table->index('order_id');
            $table->index('order_item_id');
            $table->index('product_id');
        });

        DB::statement('
            CREATE UNIQUE INDEX seat_claims_occurrence_seat_unique
            ON seat_claims (event_occurrence_id, seat_uid)
            WHERE is_zone = false
        ');

        DB::statement('
            CREATE UNIQUE INDEX seat_claims_attendee_unique
            ON seat_claims (attendee_id)
            WHERE attendee_id IS NOT NULL
        ');

        Schema::table('attendees', function (Blueprint $table) {
            $table->string('seat_uid', 24)->nullable();
            $table->string('seat_label', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn(['seat_uid', 'seat_label']);
        });

        Schema::dropIfExists('seat_claims');
    }
};
