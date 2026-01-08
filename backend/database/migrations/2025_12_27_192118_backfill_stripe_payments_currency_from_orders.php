<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<SQL
            UPDATE stripe_payments sp
            SET currency = o.currency
            FROM orders o
            WHERE sp.order_id = o.id
            AND sp.currency IS NULL
        SQL);
    }

    public function down(): void
    {
        // Cannot reverse - we don't know which records had null currency before
    }
};
