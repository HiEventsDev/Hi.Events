<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private const INDEXES = [
        'orders_created_at_index' => 'orders (created_at)',
        'accounts_created_at_index' => 'accounts (created_at)',
        'events_created_at_index' => 'events (created_at)',
        'order_payment_platform_fees_created_at_index' => 'order_payment_platform_fees (created_at)',
        'event_daily_statistics_date_index' => 'event_daily_statistics (date)',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $definition) {
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON {$definition}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
        }
    }
};
