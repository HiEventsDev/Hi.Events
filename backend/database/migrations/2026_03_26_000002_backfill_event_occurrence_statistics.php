<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO event_occurrence_statistics (
                event_id,
                event_occurrence_id,
                products_sold,
                attendees_registered,
                sales_total_gross,
                sales_total_before_additions,
                total_tax,
                total_fee,
                orders_created,
                orders_cancelled,
                total_refunded,
                version,
                created_at,
                updated_at
            )
            SELECT
                es.event_id,
                eo.id AS event_occurrence_id,
                es.products_sold,
                es.attendees_registered,
                es.sales_total_gross,
                es.sales_total_before_additions,
                es.total_tax,
                es.total_fee,
                es.orders_created,
                es.orders_cancelled,
                es.total_refunded,
                0 AS version,
                NOW(),
                NOW()
            FROM event_statistics es
            INNER JOIN event_occurrences eo ON eo.event_id = es.event_id AND eo.deleted_at IS NULL
            WHERE es.deleted_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM event_occurrence_statistics eos
                  WHERE eos.event_occurrence_id = eo.id
                    AND eos.deleted_at IS NULL
              )
              AND (
                  SELECT COUNT(*) FROM event_occurrences eo2
                  WHERE eo2.event_id = es.event_id AND eo2.deleted_at IS NULL
              ) = 1
        SQL);
    }

    public function down(): void
    {
        // No rollback needed — the occurrence stats table can be truncated if needed
    }
};
