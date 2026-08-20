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
            $table->unsignedBigInteger('legacy_account_configuration_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('legacy_account_configuration_id');
        });

        DB::statement('
            INSERT INTO organizer_configurations
                (name, is_system_default, application_fees, bypass_application_fees,
                 legacy_account_configuration_id, created_at, updated_at)
            SELECT name, is_system_default, application_fees, bypass_application_fees,
                   id, NOW(), NOW()
            FROM account_configuration
            WHERE deleted_at IS NULL
        ');

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
