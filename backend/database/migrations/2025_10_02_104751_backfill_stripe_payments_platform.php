<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill existing stripe payments with 'ca' platform for Hi.Events cloud installations
        // All existing payments on the cloud platform were processed using the Canada Stripe platform
        if (config('app.is_hi_events')) {
            DB::table('stripe_payments')
                ->whereNull('stripe_platform')
                ->update(['stripe_platform' => 'ca']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the backfill for Hi.Events cloud installations
        if (config('app.is_hi_events')) {
            DB::table('stripe_payments')
                ->where('stripe_platform', 'ca')
                ->update(['stripe_platform' => null]);
        }
    }
};
