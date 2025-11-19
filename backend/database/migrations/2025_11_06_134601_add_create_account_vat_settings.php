<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_vat_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->boolean('vat_registered')->default(false);
            $table->string('vat_number', 20)->nullable();
            $table->boolean('vat_validated')->default(false);
            $table->timestamp('vat_validation_date')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_address')->nullable();
            $table->string('vat_country_code', 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('cascade');

            $table->unique('account_id');
            $table->index('vat_number');
            $table->index('vat_validated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_vat_settings');
    }
};
