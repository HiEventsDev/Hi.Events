<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_configuration', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_system_default')->default(false);
            $table->json('application_fees')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $defaultConfigId = DB::table('account_configuration')->insertGetId([
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => json_encode([
                'percentage' => config('app.saas_stripe_application_fee_percent'),
                'fixed' => config('app.saas_stripe_application_fee_fixed') ?? 0,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('account_configuration_id')
                ->nullable()
                ->constrained('account_configuration')
                ->onDelete('set null');
        });

        DB::table('accounts')->update(['account_configuration_id' => $defaultConfigId]);

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('configuration');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['account_configuration_id']);
            $table->dropColumn('account_configuration_id');
            $table->json('configuration')->nullable();
        });

        Schema::dropIfExists('account_configuration');
    }
};
