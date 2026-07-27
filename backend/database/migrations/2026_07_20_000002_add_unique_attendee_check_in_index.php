<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE attendee_check_ins
            SET deleted_at = NOW()
            WHERE deleted_at IS NULL
              AND id NOT IN (
                  SELECT MIN(id)
                  FROM attendee_check_ins
                  WHERE deleted_at IS NULL
                  GROUP BY attendee_id, check_in_list_id
              )
        ');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS attendee_check_ins_unique_attendee_list
            ON attendee_check_ins (attendee_id, check_in_list_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendee_check_ins_unique_attendee_list');
    }
};
