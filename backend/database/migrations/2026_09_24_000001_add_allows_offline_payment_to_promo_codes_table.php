<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', static function (Blueprint $table) {
            $table->boolean('allows_offline_payment')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', static function (Blueprint $table) {
            $table->dropColumn('allows_offline_payment');
        });
    }
};
