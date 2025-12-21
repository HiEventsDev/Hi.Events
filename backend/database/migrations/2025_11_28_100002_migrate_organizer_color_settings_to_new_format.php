<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrates existing organizer homepage_theme_settings from the old 6-color format
     * to the new simplified 2-color + mode system.
     */
    public function up(): void
    {
        $organizerSettings = DB::table('organizer_settings')->get();

        foreach ($organizerSettings as $settings) {
            $existingThemeSettings = $settings->homepage_theme_settings
                ? json_decode($settings->homepage_theme_settings, true)
                : [];

            // Skip if already in new format
            if (isset($existingThemeSettings['accent'])) {
                continue;
            }

            $background = $existingThemeSettings['homepage_background_color'] ?? '#f5f3ff';
            $mode = $this->detectMode($background);

            $newThemeSettings = [
                'accent' => $existingThemeSettings['homepage_primary_color'] ?? '#8b5cf6',
                'background' => $background,
                'mode' => $mode,
                'background_type' => $existingThemeSettings['homepage_background_type'] ?? 'COLOR',
            ];

            DB::table('organizer_settings')
                ->where('id', $settings->id)
                ->update(['homepage_theme_settings' => json_encode($newThemeSettings)]);
        }
    }

    /**
     * Detect whether a background color should use light or dark mode
     * based on its luminance value (WCAG formula).
     */
    private function detectMode(string $backgroundColor): string
    {
        $hex = ltrim($backgroundColor, '#');

        // Handle short hex formats and colors with alpha
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        } elseif (strlen($hex) === 4) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        } elseif (strlen($hex) === 8) {
            $hex = substr($hex, 0, 6);
        }

        if (strlen($hex) !== 6) {
            return 'light'; // Default to light mode for invalid colors
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate luminance using WCAG formula
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);

        return $luminance > 128 ? 'light' : 'dark';
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to old format
        $organizerSettings = DB::table('organizer_settings')->get();

        foreach ($organizerSettings as $settings) {
            $existingThemeSettings = $settings->homepage_theme_settings
                ? json_decode($settings->homepage_theme_settings, true)
                : [];

            // Skip if already in old format
            if (isset($existingThemeSettings['homepage_primary_color'])) {
                continue;
            }

            // Convert from new format to old format
            $oldThemeSettings = [
                'homepage_background_color' => $existingThemeSettings['background'] ?? '#f5f3ff',
                'homepage_content_background_color' => $existingThemeSettings['mode'] === 'dark' ? '#1f1f1f' : '#ffffff',
                'homepage_primary_color' => $existingThemeSettings['accent'] ?? '#8b5cf6',
                'homepage_primary_text_color' => $existingThemeSettings['mode'] === 'dark' ? '#ffffff' : '#1a1a1a',
                'homepage_secondary_color' => $existingThemeSettings['mode'] === 'dark' ? '#a3a3a3' : '#525252',
                'homepage_secondary_text_color' => $existingThemeSettings['mode'] === 'dark' ? '#737373' : '#737373',
                'homepage_background_type' => $existingThemeSettings['background_type'] ?? 'COLOR',
            ];

            DB::table('organizer_settings')
                ->where('id', $settings->id)
                ->update(['homepage_theme_settings' => json_encode($oldThemeSettings)]);
        }
    }
};
