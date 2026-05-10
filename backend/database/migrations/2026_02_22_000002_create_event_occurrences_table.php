<?php

use HiEvents\Helper\IdHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->string('short_id')->index();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->integer('capacity')->nullable();
            $table->integer('used_capacity')->default(0);
            $table->string('label', 255)->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('start_date');
            $table->index('status');
            $table->index(['event_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');
    }
};
