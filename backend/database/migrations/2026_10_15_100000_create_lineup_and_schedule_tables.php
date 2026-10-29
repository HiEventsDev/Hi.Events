<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('tagline', 255)->nullable();
            $table->text('bio')->nullable();
            $table->jsonb('links')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('organizer_id');
        });

        Schema::create('event_lineup_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('billing_tier', 20)->default('STANDARD');
            $table->string('role_label', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->unique(['event_id', 'profile_id']);
        });

        Schema::create('event_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_location_id')->nullable()->constrained('event_locations')->nullOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->index('event_id');
        });

        Schema::create('event_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_space_id')->nullable()->constrained('event_spaces')->nullOnDelete();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['event_id', 'starts_at']);
        });

        Schema::create('event_schedule_item_performers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_schedule_item_id')->constrained('event_schedule_items')->cascadeOnDelete();
            $table->foreignId('event_lineup_entry_id')->constrained('event_lineup_entries')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['event_schedule_item_id', 'event_lineup_entry_id'], 'schedule_item_performers_unique');
            $table->index('event_lineup_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_schedule_item_performers');
        Schema::dropIfExists('event_schedule_items');
        Schema::dropIfExists('event_spaces');
        Schema::dropIfExists('event_lineup_entries');
        Schema::dropIfExists('profiles');
    }
};
