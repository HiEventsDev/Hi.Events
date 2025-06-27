<?php

use HiEvents\DomainObjects\Enums\ColorTheme;
use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use HiEvents\Models\Organizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        $defaultTheme = config('app.organizer_homepage_default_theme');

        // Create default settings for all existing organizers
        DB::transaction(static function () use ($defaultTheme) {
            Organizer::all()->each(static function (Organizer $organizer) use ($defaultTheme) {
                /** @var ColorTheme $defaultTheme */
                $defaultThemeColors = $defaultTheme->getThemeData();

                $organizer->organizer_settings()->create([
                    'homepage_visibility' => OrganizerHomepageVisibility::PUBLIC->name,

                    'homepage_theme_settings' => [
                        'homepage_background_color' => $defaultThemeColors['homepage_background_color'] ?? '#2c0838',
                        'homepage_content_background_color' => $defaultThemeColors['homepage_content_background_color'] ?? '#32174f',
                        'homepage_primary_color' => $defaultThemeColors['homepage_primary_color'] ?? '#c7a2db',
                        'homepage_primary_text_color' => $defaultThemeColors['homepage_primary_text_color'] ?? '#ffffff',
                        'homepage_secondary_color' => $defaultThemeColors['homepage_secondary_color'] ?? '#c7a2db',
                        'homepage_secondary_text_color' => $defaultThemeColors['homepage_secondary_text_color'] ?? '#ffffff',
                    ],
                ]);
            });
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_settings');
    }
};
