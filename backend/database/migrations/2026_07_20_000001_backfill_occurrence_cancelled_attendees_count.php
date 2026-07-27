<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE event_occurrences eo
            SET cancelled_attendees_count = (
                SELECT COUNT(*)
                FROM attendees a
                INNER JOIN orders o ON o.id = a.order_id
                WHERE a.event_occurrence_id = eo.id
                  AND a.status = 'CANCELLED'
                  AND a.deleted_at IS NULL
                  AND o.status IN ('COMPLETED', 'AWAITING_OFFLINE_PAYMENT')
            )
            WHERE eo.status = 'CANCELLED'
              AND eo.cancelled_attendees_count IS NULL
        ");
    }

    public function down(): void {}
};
