<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->integer('invoice_payment_terms_days')->nullable();
            $table->text('invoice_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('invoice_payment_terms_days');
            $table->dropColumn('invoice_notes');
        });
    }
};
