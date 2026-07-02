<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_system_default')->default(false);
            $table->json('application_fees')->nullable();
            $table->boolean('bypass_application_fees')->default(false);
            // Tracks the source row in the legacy account_configuration table so
            // backfill + new-organizer assignment can match by id, not by name.
            // Dropped in the follow-up that retires the legacy table.
            $table->unsignedBigInteger('legacy_account_configuration_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('legacy_account_configuration_id');
        });

        // Copy every non-deleted account_configuration row 1:1 into organizer_configurations,
        // preserving the legacy id pointer so we can map organizers deterministically.
        DB::statement('
            INSERT INTO organizer_configurations
                (name, is_system_default, application_fees, bypass_application_fees,
                 legacy_account_configuration_id, created_at, updated_at)
            SELECT name, is_system_default, application_fees, bypass_application_fees,
                   id, NOW(), NOW()
            FROM account_configuration
            WHERE deleted_at IS NULL
        ');

        // Ensure there's always a system default. If the legacy table didn't have one
        // (fresh open-source install), seed from app config.
        $hasDefault = DB::table('organizer_configurations')->where('is_system_default', true)->exists();
        if (! $hasDefault) {
            DB::table('organizer_configurations')->insert([
                'name' => 'Default',
                'is_system_default' => true,
                'application_fees' => json_encode([
                    'percentage' => config('app.saas_stripe_application_fee_percent'),
                    'fixed' => config('app.saas_stripe_application_fee_fixed') ?? 0,
                ], JSON_THROW_ON_ERROR),
                'bypass_application_fees' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $defaultConfigId = DB::table('organizer_configurations')
            ->where('is_system_default', true)
            ->orderBy('id', 'asc')
            ->value('id');

        Schema::table('organizers', function (Blueprint $table) {
            $table->foreignId('organizer_configuration_id')
                ->nullable()
                ->constrained('organizer_configurations')
                ->onDelete('set null');
        });

        // Map each organizer to the new row that mirrors its parent account's plan.
        // Falls back to the system default if the account had no plan assigned.
        DB::statement('
            UPDATE organizers o
            SET organizer_configuration_id = COALESCE((
                SELECT oc.id
                FROM organizer_configurations oc
                JOIN accounts a ON a.account_configuration_id = oc.legacy_account_configuration_id
                WHERE a.id = o.account_id
                LIMIT 1
            ), ?)
            WHERE o.deleted_at IS NULL
        ', [$defaultConfigId]);
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropForeign(['organizer_configuration_id']);
            $table->dropColumn('organizer_configuration_id');
        });

        Schema::dropIfExists('organizer_configurations');
    }
};
