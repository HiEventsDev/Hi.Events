<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_vat_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->boolean('vat_registered')->default(false);
            $table->string('vat_number', 20)->nullable();
            $table->boolean('vat_validated')->default(false);
            $table->string('vat_validation_status', 20)->default('PENDING');
            $table->text('vat_validation_error')->nullable();
            $table->unsignedInteger('vat_validation_attempts')->default(0);
            $table->timestamp('vat_validation_date')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_address')->nullable();
            $table->string('vat_country_code', 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organizer_id')
                ->references('id')
                ->on('organizers')
                ->onDelete('cascade');

            $table->unique('organizer_id');
            $table->index('vat_number');
            $table->index('vat_validated');
            $table->index('vat_validation_status');
        });

        DB::statement('
            INSERT INTO organizer_vat_settings (
                organizer_id, vat_registered, vat_number, vat_validated, vat_validation_status,
                vat_validation_error, vat_validation_attempts, vat_validation_date,
                business_name, business_address, vat_country_code, created_at, updated_at
            )
            SELECT DISTINCT ON (o.id)
                o.id,
                avs.vat_registered,
                avs.vat_number,
                avs.vat_validated,
                avs.vat_validation_status,
                avs.vat_validation_error,
                avs.vat_validation_attempts,
                avs.vat_validation_date,
                avs.business_name,
                avs.business_address,
                avs.vat_country_code,
                NOW(),
                NOW()
            FROM organizers o
            JOIN account_vat_settings avs ON avs.account_id = o.account_id
            WHERE avs.deleted_at IS NULL
              AND o.deleted_at IS NULL
            ORDER BY o.id, avs.id
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_vat_settings');
    }
};
