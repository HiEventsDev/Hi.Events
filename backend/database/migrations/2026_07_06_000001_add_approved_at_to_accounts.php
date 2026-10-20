<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('account_verified_at');
        });

        // Backfill existing accounts as approved
        DB::table('accounts')->whereNull('approved_at')->update([
            'approved_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
