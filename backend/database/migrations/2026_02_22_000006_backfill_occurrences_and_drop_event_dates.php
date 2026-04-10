<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // Step 1: Create one occurrence per existing event (with short_id)
            DB::table('events')->select('id', 'start_date', 'end_date', 'created_at')->orderBy('id')->chunk(500, function ($events) {
                foreach ($events as $event) {
                    DB::table('event_occurrences')->insert([
                        'event_id' => $event->id,
                        'short_id' => \HiEvents\Helper\IdHelper::shortId(\HiEvents\Helper\IdHelper::OCCURRENCE_PREFIX),
                        'start_date' => $event->start_date ?? $event->created_at ?? now(),
                        'end_date' => $event->end_date,
                        'status' => 'ACTIVE',
                        'used_capacity' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            // Step 2: Backfill order_items.event_occurrence_id
            DB::statement("
                UPDATE order_items oi
                SET event_occurrence_id = (
                    SELECT eo.id FROM event_occurrences eo
                    JOIN products p ON p.event_id = eo.event_id
                    WHERE p.id = oi.product_id
                    LIMIT 1
                )
                WHERE oi.event_occurrence_id IS NULL
            ");

            // Step 3: Backfill attendees.event_occurrence_id
            DB::statement("
                UPDATE attendees a
                SET event_occurrence_id = (
                    SELECT eo.id FROM event_occurrences eo
                    WHERE eo.event_id = a.event_id
                    LIMIT 1
                )
                WHERE a.event_occurrence_id IS NULL
            ");

            // Step 4: Make attendees NOT NULL (check_in_lists and order_items stay nullable)
            // order_items stays nullable to support future series passes (one order_item covering multiple occurrences)
            Schema::table('attendees', function (Blueprint $table) {
                $table->foreignId('event_occurrence_id')->nullable(false)->change();
            });

            // Step 5: Drop start_date and end_date from events
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn(['start_date', 'end_date']);
            });
        });
    }

    public function down(): void
    {
        // Re-add date columns to events
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
        });

        // Restore dates from occurrences
        DB::statement("
            UPDATE events e
            SET start_date = (
                SELECT MIN(eo.start_date) FROM event_occurrences eo WHERE eo.event_id = e.id
            ),
            end_date = (
                SELECT MAX(eo.end_date) FROM event_occurrences eo WHERE eo.event_id = e.id
            )
        ");

        // Null out occurrence FKs and make nullable again
        DB::statement("UPDATE attendees SET event_occurrence_id = NULL");

        Schema::table('attendees', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')->nullable()->change();
        });
    }
};
