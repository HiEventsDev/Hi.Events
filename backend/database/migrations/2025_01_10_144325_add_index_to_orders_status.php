<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::commit();
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_orders_status ON orders (status);');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_orders_refund_status ON orders (refund_status);');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_orders_payment_status ON orders (payment_status);');

    }

    public function down(): void
    {
        DB::commit();
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_orders_status;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_orders_refund_status;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_orders_payment_status;');
    }
};
