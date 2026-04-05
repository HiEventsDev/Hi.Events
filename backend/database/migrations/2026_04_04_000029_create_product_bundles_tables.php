<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->comment('Bundle price');
            $table->string('currency', 3)->default('USD');
            $table->integer('max_per_order')->nullable();
            $table->integer('quantity_available')->nullable();
            $table->integer('quantity_sold')->default(0);
            $table->string('sale_start_date')->nullable();
            $table->string('sale_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_bundle_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_price_id')->nullable();
            $table->integer('quantity')->default(1)->comment('Number of this product included');
            $table->timestamps();

            $table->index('product_bundle_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
    }
};
