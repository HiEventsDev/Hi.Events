<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_stripe_platforms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('stripe_connect_account_type')->nullable();
            $table->string('stripe_connect_platform', 2)->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->timestamp('stripe_setup_completed_at')->nullable();
            $table->jsonb('stripe_account_details')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organizer_id')->references('id')->on('organizers')->onDelete('cascade');
            $table->index(['organizer_id', 'stripe_connect_platform']);
            $table->index('stripe_account_id');
            $table->index('stripe_connect_platform');
        });

        // Preserve original created_at so getPrimaryStripePlatform()'s sortByDesc(created_at)
        // is deterministic when an account had multiple platform rows (e.g. IE + US).
        // Without this, every backfilled row shares NOW() and ties pick non-deterministically.
        DB::statement("
            INSERT INTO organizer_stripe_platforms (
                organizer_id,
                stripe_connect_account_type,
                stripe_connect_platform,
                stripe_account_id,
                stripe_setup_completed_at,
                stripe_account_details,
                created_at,
                updated_at
            )
            SELECT
                o.id,
                asp.stripe_connect_account_type,
                asp.stripe_connect_platform,
                asp.stripe_account_id,
                asp.stripe_setup_completed_at,
                asp.stripe_account_details,
                asp.created_at,
                NOW()
            FROM organizers o
            JOIN account_stripe_platforms asp ON asp.account_id = o.account_id
            WHERE asp.deleted_at IS NULL
              AND o.deleted_at IS NULL
              AND asp.stripe_account_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_stripe_platforms');
    }
};
