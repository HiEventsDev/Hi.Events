<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('certificate_enabled')->default(false);
            $table->string('certificate_title')->nullable()->comment('Certificate heading text');
            $table->text('certificate_body_template')->nullable()->comment('Liquid template for certificate body');
            $table->string('certificate_signatory_name')->nullable();
            $table->string('certificate_signatory_title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_enabled',
                'certificate_title',
                'certificate_body_template',
                'certificate_signatory_name',
                'certificate_signatory_title',
            ]);
        });
    }
};
