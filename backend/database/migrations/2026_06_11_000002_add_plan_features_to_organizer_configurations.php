<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizer_configurations', static function (Blueprint $table) {
            $table->json('features')->nullable();
            $table->foreignId('upgrades_to_id')
                ->nullable()
                ->constrained('organizer_configurations')
                ->nullOnDelete();
            $table->index('upgrades_to_id');
        });

        $proId = DB::table('organizer_configurations')->insertGetId([
            'name' => 'Pro',
            'is_system_default' => false,
            'application_fees' => json_encode([
                'percentage' => (float) config('app.saas_stripe_application_fee_percent'),
                'fixed' => (float) (config('app.saas_stripe_application_fee_fixed') ?? 0) + 0.50,
            ]),
            'features' => json_encode(['branding_removal' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organizer_configurations')
            ->where('is_system_default', true)
            ->update(['upgrades_to_id' => $proId]);
    }

    public function down(): void
    {
        DB::table('organizer_configurations')->update(['upgrades_to_id' => null]);

        DB::table('organizer_configurations')
            ->whereNull('deleted_at')
            ->where('name', 'Pro')
            ->where('is_system_default', false)
            ->whereRaw("features->>'branding_removal' = 'true'")
            ->whereNotIn('id', static function ($query) {
                $query->select('organizer_configuration_id')
                    ->from('organizers')
                    ->whereNotNull('organizer_configuration_id');
            })
            ->delete();

        Schema::table('organizer_configurations', static function (Blueprint $table) {
            $table->dropForeign(['upgrades_to_id']);
            $table->dropIndex(['upgrades_to_id']);
            $table->dropColumn(['features', 'upgrades_to_id']);
        });
    }
};
