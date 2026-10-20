<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('addon_product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'addon_product_id']);
            $table->index('addon_product_id');
        });

        DB::statement('ALTER TABLE product_addons ADD CONSTRAINT product_addons_no_self_reference CHECK (product_id <> addon_product_id)');

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_addon_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_addon_only');
        });

        Schema::dropIfExists('product_addons');
    }
};
