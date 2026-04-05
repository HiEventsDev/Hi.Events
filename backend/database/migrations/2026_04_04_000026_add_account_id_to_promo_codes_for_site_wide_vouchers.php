<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('event_id')->comment('For site-wide vouchers');
        });

        // Make event_id nullable for site-wide vouchers
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn('account_id');
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
        });
    }
};
