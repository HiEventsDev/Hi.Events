<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_occurrence_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_occurrence_id')->constrained('event_occurrences')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['event_occurrence_id', 'product_id'],
                'pov_occurrence_product_unique'
            );
            $table->index('event_occurrence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_occurrence_visibility');
    }
};
