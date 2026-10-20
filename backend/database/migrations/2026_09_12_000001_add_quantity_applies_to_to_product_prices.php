<?php

use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->string('quantity_applies_to', 20)
                ->default(ProductQuantityAppliesTo::OCCURRENCE->name)
                ->after('initial_quantity_available');
        });

        DB::table('product_prices')->update([
            'quantity_applies_to' => ProductQuantityAppliesTo::EVENT->name,
        ]);

        Schema::table('product_price_occurrence_overrides', function (Blueprint $table) {
            $table->integer('quantity_available')->nullable()->after('price');
            $table->decimal('price', 14, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('product_price_occurrence_overrides')->whereNull('price')->delete();

        Schema::table('product_price_occurrence_overrides', function (Blueprint $table) {
            $table->dropColumn('quantity_available');
            $table->decimal('price', 14, 2)->nullable(false)->change();
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn('quantity_applies_to');
        });
    }
};
