<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");

        Schema::create('event_seat_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('seat_map_id')->nullable()->constrained('seat_maps')->nullOnDelete();
            $table->unsignedInteger('source_version')->nullable();
            $table->jsonb('layout');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('prevent_orphan_seats')->default(false);
            $table->unsignedSmallInteger('max_seats_per_order')->nullable();
            $table->boolean('allow_seat_change')->default(false);
            $table->timestamps();
        });

        Schema::create('event_seat_map_band_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_seat_map_id')->constrained('event_seat_maps')->cascadeOnDelete();
            $table->string('band_key', 24);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('price_adjustment')->default(0);
            $table->timestamps();

            $table->unique(['event_seat_map_id', 'band_key', 'product_id'], 'event_seat_map_band_products_unique');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_seat_map_band_products');
        Schema::dropIfExists('event_seat_maps');
    }
};
