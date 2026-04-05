<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->boolean('is_hidden_without_promo_code')->default(false)->after('is_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn('is_hidden_without_promo_code');
        });
    }
};
