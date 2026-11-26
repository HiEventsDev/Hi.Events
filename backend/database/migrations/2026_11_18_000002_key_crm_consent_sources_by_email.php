<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = ['crm_consent_events', 'crm_sms_consent_events'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP INDEX {$table}_source_unique");
            DB::statement("
                CREATE UNIQUE INDEX {$table}_source_unique
                ON {$table} (contact_id, action, source_type, source_id, email)
                WHERE source_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP INDEX {$table}_source_unique");
            DB::statement("
                DELETE FROM {$table} newer
                USING {$table} older
                WHERE newer.contact_id = older.contact_id
                  AND newer.action = older.action
                  AND newer.source_type = older.source_type
                  AND newer.source_id = older.source_id
                  AND newer.id > older.id
            ");
            DB::statement("
                CREATE UNIQUE INDEX {$table}_source_unique
                ON {$table} (contact_id, action, source_type, source_id)
                WHERE source_id IS NOT NULL
            ");
        }
    }
};
