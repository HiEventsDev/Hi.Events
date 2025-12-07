<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_vat_settings', function (Blueprint $table) {
            $table->string('vat_validation_status', 20)->default('PENDING')->after('vat_validated');
            $table->text('vat_validation_error')->nullable()->after('vat_validation_status');
            $table->unsignedInteger('vat_validation_attempts')->default(0)->after('vat_validation_error');
        });

        DB::table('account_vat_settings')
            ->where('vat_validated', true)
            ->update(['vat_validation_status' => 'VALID']);

        DB::table('account_vat_settings')
            ->where('vat_validated', false)
            ->whereNotNull('vat_number')
            ->update(['vat_validation_status' => 'INVALID']);
    }

    public function down(): void
    {
        Schema::table('account_vat_settings', function (Blueprint $table) {
            $table->dropColumn(['vat_validation_status', 'vat_validation_error', 'vat_validation_attempts']);
        });
    }
};
