<?php

use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use HiEvents\Models\Organizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizer_settings', static function (Blueprint $table) {
            $table->id();

            $table->jsonb('social_media_handles')->nullable();

            $table->string('website_url', 255)->nullable();

            $table->jsonb('homepage_theme_settings')->nullable();
            $table->string('homepage_visibility')->default(OrganizerHomepageVisibility::PUBLIC->name);
            $table->string('homepage_password')->nullable();

            $table->string('seo_keywords', 255)->nullable();
            $table->string('seo_title', 355)->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('allow_search_engine_indexing')->default(true);

            $table->foreignId('organizer_id')
                ->constrained('organizers')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Organizer::all()->each(static function (Organizer $organizer) {
            $organizer->organizer_settings()->create();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_settings');
    }
};
