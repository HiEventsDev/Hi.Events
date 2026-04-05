<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add upsell fields to products table
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_upsell')->default(false)->comment('Show as upsell after primary products selected');
            $table->json('upsell_for_product_ids')->nullable()->comment('Product IDs that trigger this upsell, null = all');
            $table->string('upsell_display_text')->nullable()->comment('Custom upsell prompt text');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_upsell', 'upsell_for_product_ids', 'upsell_display_text']);
        });
    }
};
