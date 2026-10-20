<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE event_occurrences eo
            SET used_capacity = COALESCE((
                SELECT COUNT(*)
                FROM attendees a
                JOIN orders o ON o.id = a.order_id
                WHERE a.event_occurrence_id = eo.id
                  AND a.deleted_at IS NULL
                  AND a.status IN ('ACTIVE', 'AWAITING_PAYMENT')
                  AND o.deleted_at IS NULL
                  AND o.status IN ('COMPLETED', 'AWAITING_OFFLINE_PAYMENT')
            ), 0)
        SQL);
    }

    public function down(): void {}
};
