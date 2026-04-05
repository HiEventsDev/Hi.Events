<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_charts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('layout')->nullable(); // JSON canvas layout data
            $table->integer('total_seats')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::create('seating_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seating_chart_id')->index();
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('color', 7)->default('#CD58DD');
            $table->integer('capacity')->default(0);
            $table->integer('row_count')->default(0);
            $table->integer('seats_per_row')->default(0);
            $table->json('position')->nullable(); // {x, y, rotation}
            $table->string('shape')->default('rectangle'); // rectangle, arc, circle
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('seating_chart_id')->references('id')->on('seating_charts')->cascadeOnDelete();
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seating_section_id')->index();
            $table->unsignedBigInteger('seating_chart_id')->index();
            $table->string('row_label', 10); // A, B, C... or 1, 2, 3...
            $table->integer('seat_number');
            $table->string('label')->nullable(); // e.g., "A-12", "VIP-3"
            $table->string('status')->default('available'); // available, reserved, held, sold, disabled
            $table->unsignedBigInteger('attendee_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->decimal('price_override', 14, 2)->nullable();
            $table->string('category')->nullable(); // VIP, Standard, etc.
            $table->json('position')->nullable(); // {x, y} for custom positioned seats
            $table->boolean('is_accessible')->default(false);
            $table->boolean('is_aisle')->default(false);
            $table->timestamps();

            $table->unique(['seating_section_id', 'row_label', 'seat_number']);
            $table->foreign('seating_section_id')->references('id')->on('seating_sections')->cascadeOnDelete();
            $table->foreign('seating_chart_id')->references('id')->on('seating_charts')->cascadeOnDelete();
            $table->foreign('attendee_id')->references('id')->on('attendees')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('seating_sections');
        Schema::dropIfExists('seating_charts');
    }
};
