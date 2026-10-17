<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS user_identities_account_sso_connection_id_index ON user_identities (account_sso_connection_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS user_identities_account_sso_connection_id_index');
    }
};
