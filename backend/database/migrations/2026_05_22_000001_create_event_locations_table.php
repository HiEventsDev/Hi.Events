<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_locations', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 20)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('type', 20)->default('IN_PERSON');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->text('online_event_connection_details')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_locations');
    }
};
