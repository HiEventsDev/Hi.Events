<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->boolean('enable_invoicing')->default(false);
            $table->string('invoice_label')->nullable();
            $table->string('invoice_prefix')->nullable();
            $table->unsignedInteger('invoice_start_number')->default(1);
            $table->boolean('require_billing_address')->default(true);
            $table->string('organization_name')->nullable();
            $table->text('organization_address')->nullable();
            $table->text('invoice_tax_details')->nullable();
            $table->json('payment_providers')->nullable();
            $table->text('offline_payment_instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn([
                'enable_invoicing',
                'invoice_label',
                'invoice_prefix',
                'invoice_start_number',
                'require_billing_address',
                'organization_name',
                'organization_address',
                'invoice_tax_details',
                'payment_providers',
                'offline_payment_instructions'
            ]);
        });
    }
};
