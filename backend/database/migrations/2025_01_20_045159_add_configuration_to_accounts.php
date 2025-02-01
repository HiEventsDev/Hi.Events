<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', static function (Blueprint $table) {
            $table->json('configuration')->nullable();
        });

        DB::table('accounts')->update(['configuration' => [
            'application_fee' => [
                'percentage' => config('app.saas_stripe_application_fee_percent'),
                'fixed' => config('app.saas_stripe_application_fee_fixed') ?? 0,
            ]
        ]]);
    }

    public function down(): void
    {
        Schema::table('accounts', static function (Blueprint $table) {
            $table->dropColumn('configuration');
        });
    }
};
