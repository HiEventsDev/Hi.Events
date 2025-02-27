<?php

use HiEvents\DomainObjects\Enums\StripeConnectAccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('stripe_connect_account_type')->nullable();
        });

        DB::table('accounts')
            ->whereNotNull('stripe_account_id')
            ->update(['stripe_connect_account_type' => StripeConnectAccountType::EXPRESS->value]);
    }

    public function down(): void
    {
        Schema::table('accounts', static function (Blueprint $table) {
            $table->dropColumn('stripe_connect_account_type');
        });
    }
};
