<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stripe_payments_order_id_index ON stripe_payments (order_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stripe_payments_payment_intent_id_index ON stripe_payments (payment_intent_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stripe_payments_order_id_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stripe_payments_payment_intent_id_index');
    }
};
