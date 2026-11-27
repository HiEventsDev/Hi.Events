<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private const INDEXES = [
        'orders_event_id_updated_at_index' => 'orders (event_id, updated_at)',
        'orders_event_id_deleted_at_index' => 'orders (event_id, deleted_at) WHERE deleted_at IS NOT NULL',
        'orders_event_id_id_live_index' => 'orders (event_id, id) WHERE deleted_at IS NULL',
        'attendees_event_id_updated_at_index' => 'attendees (event_id, updated_at)',
        'attendees_event_id_deleted_at_index' => 'attendees (event_id, deleted_at) WHERE deleted_at IS NOT NULL',
        'attendee_check_ins_event_id_created_at_index' => 'attendee_check_ins (event_id, created_at)',
        'attendee_check_ins_event_id_deleted_at_index' => 'attendee_check_ins (event_id, deleted_at) WHERE deleted_at IS NOT NULL',
        'orders_event_id_reserved_until_reserved_index' => "orders (event_id, reserved_until) WHERE status = 'RESERVED' AND deleted_at IS NULL",
        'waitlist_entries_event_id_updated_at_index' => 'waitlist_entries (event_id, updated_at)',
        'waitlist_entries_event_id_deleted_at_index' => 'waitlist_entries (event_id, deleted_at) WHERE deleted_at IS NOT NULL',
        'email_deliveries_crm_feedback_index' => "email_deliveries (updated_at) WHERE complained_at IS NOT NULL OR (bounced_at IS NOT NULL AND bounce_type = 'PERMANENT')",
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $definition) {
            $this->dropIfInvalid($name);
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON {$definition}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
        }
    }

    private function dropIfInvalid(string $name): void
    {
        $invalid = DB::selectOne(
            'SELECT 1 AS invalid FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid WHERE c.relname = ? AND NOT i.indisvalid',
            [$name],
        );

        if ($invalid !== null) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
        }
    }
};
