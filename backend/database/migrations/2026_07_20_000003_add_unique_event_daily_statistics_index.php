<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE event_daily_statistics t SET
                products_sold = agg.products_sold,
                attendees_registered = agg.attendees_registered,
                sales_total_gross = agg.sales_total_gross,
                sales_total_before_additions = agg.sales_total_before_additions,
                total_tax = agg.total_tax,
                total_fee = agg.total_fee,
                total_refunded = agg.total_refunded,
                total_views = agg.total_views,
                orders_created = agg.orders_created,
                orders_cancelled = agg.orders_cancelled
            FROM (
                SELECT event_id, date, MIN(id) AS keep_id,
                    SUM(products_sold) AS products_sold,
                    SUM(attendees_registered) AS attendees_registered,
                    SUM(sales_total_gross) AS sales_total_gross,
                    SUM(sales_total_before_additions) AS sales_total_before_additions,
                    SUM(total_tax) AS total_tax,
                    SUM(total_fee) AS total_fee,
                    SUM(total_refunded) AS total_refunded,
                    SUM(total_views) AS total_views,
                    SUM(orders_created) AS orders_created,
                    SUM(orders_cancelled) AS orders_cancelled
                FROM event_daily_statistics
                WHERE deleted_at IS NULL
                GROUP BY event_id, date
                HAVING COUNT(*) > 1
            ) agg
            WHERE t.id = agg.keep_id
        ');

        DB::statement('
            DELETE FROM event_daily_statistics d
            USING (
                SELECT event_id, date, MIN(id) AS keep_id
                FROM event_daily_statistics
                WHERE deleted_at IS NULL
                GROUP BY event_id, date
                HAVING COUNT(*) > 1
            ) agg
            WHERE d.deleted_at IS NULL
              AND d.event_id = agg.event_id
              AND d.date = agg.date
              AND d.id <> agg.keep_id
        ');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS event_daily_statistics_event_id_date_uniq
            ON event_daily_statistics (event_id, date)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS event_daily_statistics_event_id_date_uniq');
    }
};
