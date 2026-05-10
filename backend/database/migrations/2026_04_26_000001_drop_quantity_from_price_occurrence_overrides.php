<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_price_occurrence_overrides', 'quantity_available')) {
            return;
        }

        Schema::table('product_price_occurrence_overrides', function (Blueprint $table) {
            $table->dropColumn('quantity_available');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_price_occurrence_overrides', 'quantity_available')) {
            return;
        }

        Schema::table('product_price_occurrence_overrides', function (Blueprint $table) {
            $table->integer('quantity_available')->nullable()->after('price');
        });
    }
};
