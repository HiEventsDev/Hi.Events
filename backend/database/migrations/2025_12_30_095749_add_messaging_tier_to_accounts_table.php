<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TIER_UNTRUSTED = 1;
    private const TIER_PREMIUM = 3;

    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('account_messaging_tier_id')
                ->nullable()
                ->constrained('account_messaging_tiers')
                ->nullOnDelete();
        });

        if (!config('app.is_hi_events')) {
            // Self-hosted: set all accounts to Premium tier
            DB::table('accounts')
                ->whereNull('account_messaging_tier_id')
                ->update(['account_messaging_tier_id' => self::TIER_PREMIUM]);
        } else {
            DB::table('accounts')
                ->whereNull('account_messaging_tier_id')
                ->where('is_manually_verified', true)
                ->update(['account_messaging_tier_id' => self::TIER_PREMIUM]);

            DB::table('accounts')
                ->whereNull('account_messaging_tier_id')
                ->update(['account_messaging_tier_id' => self::TIER_UNTRUSTED]);
        }
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_messaging_tier_id');
        });
    }
};
