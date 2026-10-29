<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");

        DB::table('feature_flags')->insertOrIgnore([
            'key' => 'enterprise_sso',
            'enabled_by_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('key', 'enterprise_sso')->delete();
    }
};
