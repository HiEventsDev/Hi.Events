<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->string('sales_report_frequency')->nullable()->comment('DAILY, WEEKLY, MONTHLY or null for disabled');
            $table->json('sales_report_recipient_emails')->nullable()->comment('Array of email addresses to receive sales reports');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['sales_report_frequency', 'sales_report_recipient_emails']);
        });
    }
};
