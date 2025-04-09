<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::commit();
        $psql = DB::getDriverName() === 'pgsql' ? "CONCURRENTLY IF NOT EXISTS" : "";
        $myslCharLimit = DB::getDriverName() === 'mysql' ? "(187)" : "";
        DB::statement("CREATE INDEX $psql idx_orders_status ON orders (status{$myslCharLimit});");
        DB::statement("CREATE INDEX $psql idx_orders_refund_status ON orders (refund_status{$myslCharLimit});");
        DB::statement("CREATE INDEX $psql idx_orders_payment_status ON orders (payment_status{$myslCharLimit});");

    }

    public function down(): void
    {
        DB::commit();
        $psql = DB::getDriverName() === 'pgsql' ? "CONCURRENTLY IF NOT EXISTS" : "";
        DB::statement("DROP INDEX $psql idx_orders_status;");
        DB::statement("DROP INDEX $psql idx_orders_refund_status;");
        DB::statement("DROP INDEX $psql idx_orders_payment_status;");
    }
};
