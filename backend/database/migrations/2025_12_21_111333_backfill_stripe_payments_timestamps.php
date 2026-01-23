<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE stripe_payments sp
            SET created_at = o.created_at,
                updated_at = o.created_at
            FROM orders o
            WHERE sp.order_id = o.id
            AND sp.created_at IS NULL
        ');
    }

    public function down(): void
    {
        // No rollback needed - timestamps should remain populated
    }
};
