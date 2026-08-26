<?php

use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', static function (Blueprint $table) {
            $table->string('discount_applies_to')
                ->default(PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name);
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', static function (Blueprint $table) {
            $table->dropColumn('discount_applies_to');
        });
    }
};
