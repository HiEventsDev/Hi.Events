<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('product_price_occurrence_overrides', 'deleted_at')) {
            DB::table('product_price_occurrence_overrides')
                ->whereNotNull('deleted_at')
                ->delete();
        }

        Schema::table('product_price_occurrence_overrides', function (Blueprint $table) {
            if (!Schema::hasColumn('product_price_occurrence_overrides', 'quantity_available')) {
                $table->integer('quantity_available')->nullable()->after('price');
            }
            if (Schema::hasColumn('product_price_occurrence_overrides', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_price_occurrence_overrides', function (Blueprint $table) {
            $table->dropColumn('quantity_available');
            $table->softDeletes();
        });
    }
};
