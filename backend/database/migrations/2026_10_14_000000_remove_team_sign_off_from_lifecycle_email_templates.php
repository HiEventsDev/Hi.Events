<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE lifecycle_email_templates
            SET body = regexp_replace(body, '\s*<p>The Hi\.Events team</p>\s*$', '')
            WHERE body ~ '<p>The Hi\.Events team</p>\s*$'
        SQL);
    }

    public function down(): void
    {
    }
};
