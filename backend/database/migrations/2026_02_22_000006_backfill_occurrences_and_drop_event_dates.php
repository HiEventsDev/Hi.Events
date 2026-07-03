<?php

use HiEvents\Helper\IdHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Step 1: Create one occurrence per existing event (with short_id).
            // start_date/end_date are read here and intentionally retained on the
            // events table (never dropped). Skip individual events that already have
            // an occurrence so a retry after partial completion succeeds.
            if (Schema::hasColumn('events', 'start_date')) {
                DB::table('events')->select('id', 'start_date', 'end_date', 'created_at')->orderBy('id')->chunk(500, function ($events) {
                    $eventIds = $events->pluck('id')->all();
                    $alreadySeeded = DB::table('event_occurrences')
                        ->whereIn('event_id', $eventIds)
                        ->pluck('event_id')
                        ->all();
                    $seededLookup = array_flip($alreadySeeded);

                    foreach ($events as $event) {
                        if (isset($seededLookup[$event->id])) {
                            continue;
                        }

                        DB::table('event_occurrences')->insert([
                            'event_id' => $event->id,
                            'short_id' => IdHelper::shortId(IdHelper::OCCURRENCE_PREFIX),
                            'start_date' => $event->start_date ?? $event->created_at ?? now(),
                            'end_date' => $event->end_date,
                            'status' => 'ACTIVE',
                            'used_capacity' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
            }

            // Step 2: Backfill order_items.event_occurrence_id (idempotent — WHERE IS NULL)
            DB::statement('
                UPDATE order_items oi
                SET event_occurrence_id = (
                    SELECT eo.id FROM event_occurrences eo
                    JOIN products p ON p.event_id = eo.event_id
                    WHERE p.id = oi.product_id
                    LIMIT 1
                )
                WHERE oi.event_occurrence_id IS NULL
            ');

            // Step 3: Backfill attendees.event_occurrence_id (idempotent — WHERE IS NULL)
            DB::statement('
                UPDATE attendees a
                SET event_occurrence_id = (
                    SELECT eo.id FROM event_occurrences eo
                    WHERE eo.event_id = a.event_id
                    LIMIT 1
                )
                WHERE a.event_occurrence_id IS NULL
            ');

            // Step 4: Make attendees NOT NULL (no-op if already NOT NULL).
            // order_items stays nullable to support future series passes.
            Schema::table('attendees', function (Blueprint $table) {
                $table->foreignId('event_occurrence_id')->nullable(false)->change();
            });
        });
    }

    public function down(): void
    {
        DB::statement('UPDATE attendees SET event_occurrence_id = NULL');

        Schema::table('attendees', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')->nullable()->change();
        });
    }
};
