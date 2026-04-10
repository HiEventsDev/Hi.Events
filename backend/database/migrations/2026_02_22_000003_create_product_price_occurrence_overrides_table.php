<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_price_occurrence_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_occurrence_id')->constrained('event_occurrences')->onDelete('cascade');
            $table->foreignId('product_price_id')->constrained('product_prices')->onDelete('cascade');
            $table->decimal('price', 14, 2);
            $table->timestamps();

            $table->unique(
                ['event_occurrence_id', 'product_price_id'],
                'ppoo_occurrence_price_unique'
            );
            $table->index('event_occurrence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_occurrence_overrides');
    }
};
