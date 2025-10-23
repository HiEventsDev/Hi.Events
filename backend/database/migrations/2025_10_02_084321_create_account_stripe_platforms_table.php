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
        Schema::create('account_stripe_platforms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('stripe_connect_account_type')->nullable();
            $table->string('stripe_connect_platform', 2)->nullable();
            $table->string('stripe_account_id')->nullable()->unique();
            $table->timestamp('stripe_setup_completed_at')->nullable();
            $table->jsonb('stripe_account_details')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->index(['account_id', 'stripe_connect_platform']);
            $table->index('stripe_connect_platform');
        });

        // Migrate existing data from accounts table to the new table
        // For Hi.Events installations, set platform to 'ca' (Canada), otherwise leave as NULL for open-source
        $isHiEvents = config('app.is_hi_events', false);
        $platform = $isHiEvents ? "'ca'" : 'NULL';

        DB::statement("
            INSERT INTO account_stripe_platforms (
                account_id,
                stripe_connect_account_type,
                stripe_connect_platform,
                stripe_account_id,
                stripe_setup_completed_at,
                created_at,
                updated_at
            )
            SELECT
                id,
                stripe_connect_account_type,
                {$platform},
                stripe_account_id,
                CASE
                    WHEN stripe_connect_setup_complete = true THEN NOW()
                    ELSE NULL
                END,
                NOW(),
                NOW()
            FROM accounts
            WHERE stripe_account_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migrate data back to accounts table before dropping
        DB::statement('
            UPDATE accounts a
            SET
                stripe_connect_account_type = asp.stripe_connect_account_type,
                stripe_account_id = asp.stripe_account_id,
                stripe_connect_setup_complete = CASE
                    WHEN asp.stripe_setup_completed_at IS NOT NULL THEN true
                    ELSE false
                END
            FROM account_stripe_platforms asp
            WHERE a.id = asp.account_id
        ');

        Schema::dropIfExists('account_stripe_platforms');
    }
};
