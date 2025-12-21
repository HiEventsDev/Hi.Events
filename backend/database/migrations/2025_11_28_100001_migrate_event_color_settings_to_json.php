<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $eventSettings = DB::table('event_settings')->get();

        foreach ($eventSettings as $settings) {
            $background = $settings->homepage_body_background_color ?? '#f5f3ff';
            $mode = $this->detectMode($background);

            $themeSettings = [
                'accent' => $settings->homepage_primary_color ?? '#8b5cf6',
                'background' => $background,
                'mode' => $mode,
                'background_type' => $settings->homepage_background_type ?? 'COLOR',
            ];

            DB::table('event_settings')
                ->where('id', $settings->id)
                ->update(['homepage_theme_settings' => json_encode($themeSettings)]);
        }
    }

    private function detectMode(string $backgroundColor): string
    {
        $hex = ltrim($backgroundColor, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        } elseif (strlen($hex) === 4) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        } elseif (strlen($hex) === 8) {
            $hex = substr($hex, 0, 6);
        }

        if (strlen($hex) !== 6) {
            return 'light';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);

        return $luminance > 128 ? 'light' : 'dark';
    }

    public function down(): void
    {
        DB::table('event_settings')->update(['homepage_theme_settings' => null]);
    }
};
