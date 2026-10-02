<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");

        DB::statement('ALTER TABLE attendees ALTER COLUMN email DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE attendees SET email = '' WHERE email IS NULL");
        DB::statement('ALTER TABLE attendees ALTER COLUMN email SET NOT NULL');
    }
};
