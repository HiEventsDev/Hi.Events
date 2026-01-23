<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stripe_payments', function (Blueprint $table) {
            // Add platform column to track which Stripe platform was used for this payment
            $table->string('stripe_platform', 2)->nullable()->after('connected_account_id');
            $table->index('stripe_platform');
        });

        // Backfill existing stripe payments with 'ca' platform for Hi.Events cloud installations
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
        Schema::table('stripe_payments', function (Blueprint $table) {
            $table->dropIndex(['stripe_platform']);
            $table->dropColumn('stripe_platform');
        });
    }
};
