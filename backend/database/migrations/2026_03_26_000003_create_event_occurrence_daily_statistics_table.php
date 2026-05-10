<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_occurrence_daily_statistics table and backfills from event_daily_statistics.
 *
 * This is part of a migration plan to consolidate statistics at the occurrence level:
 *
 * 1. [DONE] event_occurrence_statistics created, double-writes enabled, reads switched
 * 2. [THIS] event_occurrence_daily_statistics created, double-writes enabled, reads switched
 * 3. [TODO] Move total_views/unique_views from event_statistics to events table
 * 4. [TODO] Drop event_statistics table and remove double-writes
 * 5. [TODO] Drop event_daily_statistics table and remove double-writes
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_occurrence_daily_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('event_occurrence_id')->constrained('event_occurrences');
            $table->date('date');
            $table->integer('products_sold')->default(0);
            $table->unsignedInteger('attendees_registered')->default(0);
            $table->decimal('sales_total_gross', 14, 2)->default(0);
            $table->decimal('sales_total_before_additions', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_fee', 14, 2)->default(0);
            $table->integer('orders_created')->default(0);
            $table->unsignedInteger('orders_cancelled')->default(0);
            $table->decimal('total_refunded', 14, 2)->default(0);
            $table->integer('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'date']);
            $table->unique(['event_occurrence_id', 'date']);
        });

        // Backfill for single-occurrence events (1:1 mapping from event_daily_statistics)
        DB::statement(<<<'SQL'
            INSERT INTO event_occurrence_daily_statistics (
                event_id, event_occurrence_id, date,
                products_sold, attendees_registered,
                sales_total_gross, sales_total_before_additions,
                total_tax, total_fee,
                orders_created, orders_cancelled, total_refunded,
                version, created_at, updated_at
            )
            SELECT
                eds.event_id, eo.id, eds.date,
                eds.products_sold, eds.attendees_registered,
                eds.sales_total_gross, eds.sales_total_before_additions,
                eds.total_tax, eds.total_fee,
                eds.orders_created, eds.orders_cancelled, eds.total_refunded,
                0, NOW(), NOW()
            FROM event_daily_statistics eds
            INNER JOIN event_occurrences eo ON eo.event_id = eds.event_id AND eo.deleted_at IS NULL
            WHERE eds.deleted_at IS NULL
              AND (
                  SELECT COUNT(*) FROM event_occurrences eo2
                  WHERE eo2.event_id = eds.event_id AND eo2.deleted_at IS NULL
              ) = 1
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrence_daily_statistics');
    }
};
