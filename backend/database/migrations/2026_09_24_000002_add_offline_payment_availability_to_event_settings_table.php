<?php

use HiEvents\DomainObjects\Enums\OfflinePaymentAvailability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->string('offline_payment_availability')
                ->default(OfflinePaymentAvailability::EVERYONE->name);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('offline_payment_availability');
        });
    }
};
