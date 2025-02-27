<?php

use HiEvents\DomainObjects\Enums\PaymentProviders;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('event_settings')
            ->whereNull('payment_providers')
            ->update(['payment_providers' => [PaymentProviders::STRIPE->name]]);
    }

    public function down(): void
    {
        // noop
    }
};
