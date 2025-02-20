<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stripe_payments', static function (Blueprint $table) {
            $table->bigInteger('application_fee')
                ->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('stripe_payments', static function (Blueprint $table) {
            $table->dropColumn('application_fee');
        });
    }
};
