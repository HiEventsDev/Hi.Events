<?php

namespace HiEvents\DomainObjects\Enums;

enum ColorTheme: string
{
    case CLASSIC = 'Classic';
    case ELEGANT = 'Elegant';
    case MODERN = 'Modern';
    case OCEAN = 'Ocean';
    case FOREST = 'Forest';
    case SUNSET = 'Sunset';
    case MIDNIGHT = 'Midnight';
    case ROYAL = 'Royal';
    case CORAL = 'Coral';
    case ARCTIC = 'Arctic';
    case NOIR = 'Noir';

    public function getThemeData(): array
    {
        return match ($this) {
            self::MIDNIGHT => [
                'name' => self::MIDNIGHT->value,
                'homepage_background_color' => '#737373ff',
                'homepage_content_background_color' => '#0f172a9c',
                'homepage_primary_color' => '#ffffffff',
                'homepage_primary_text_color' => '#ffffffff',
                'homepage_secondary_color' => '#b3b3b3ff',
                'homepage_secondary_text_color' => '#ffffff',
            ],
            self::CLASSIC => [
                'name' => self::CLASSIC->value,
                'homepage_background_color' => '#fafafa',
                'homepage_content_background_color' => '#ffffffbf',
                'homepage_primary_color' => '#171717',
                'homepage_primary_text_color' => '#171717',
                'homepage_secondary_color' => '#737373',
                'homepage_secondary_text_color' => '#ffffff',
            ],
            self::ELEGANT => [
                'name' => self::ELEGANT->value,
                'homepage_background_color' => '#1a1523',
                'homepage_content_background_color' => '#2d2438bf',
                'homepage_primary_color' => '#d4af37',
                'homepage_primary_text_color' => '#f5e6d3',
                'homepage_secondary_color' => '#b8860b',
                'homepage_secondary_text_color' => '#faf0e6',
            ],
            self::MODERN => [
                'name' => self::MODERN->value,
                'homepage_background_color' => '#2c0838',
                'homepage_content_background_color' => '#32174fbf',
                'homepage_primary_color' => '#c7a2db',
                'homepage_primary_text_color' => '#ffffff',
                'homepage_secondary_color' => '#c7a2db',
                'homepage_secondary_text_color' => '#ffffff',
            ],
            self::OCEAN => [
                'name' => self::OCEAN->value,
                'homepage_background_color' => '#c3e3f7',
                'homepage_content_background_color' => '#ffffffbf',
                'homepage_primary_color' => '#0ea5e9',
                'homepage_primary_text_color' => '#075985',
                'homepage_secondary_color' => '#0891b2',
                'homepage_secondary_text_color' => '#e9f6ff',
            ],
            self::FOREST => [
                'name' => self::FOREST->value,
                'homepage_background_color' => '#91b89e',
                'homepage_content_background_color' => '#ffffffbf',
                'homepage_primary_color' => '#91b89e',
                'homepage_primary_text_color' => '#14532d',
                'homepage_secondary_color' => '#16a34a',
                'homepage_secondary_text_color' => '#eefff3',
            ],
            self::SUNSET => [
                'name' => self::SUNSET->value,
                'homepage_background_color' => '#e8c47b',
                'homepage_content_background_color' => '#ffffffbf',
                'homepage_primary_color' => '#f97316',
                'homepage_primary_text_color' => '#7c2d12',
                'homepage_secondary_color' => '#ea580c',
                'homepage_secondary_text_color' => '#fad9cd',
            ],
            self::ROYAL => [
                'name' => self::ROYAL->value,
                'homepage_background_color' => '#f3e8ff',
                'homepage_content_background_color' => '#ffffffbf',
                'homepage_primary_color' => '#a855f7',
                'homepage_primary_text_color' => '#581c87',
                'homepage_secondary_color' => '#9333ea',
                'homepage_secondary_text_color' => '#f6eeff',
            ],
            self::CORAL => [
                'name' => self::CORAL->value,
                'homepage_background_color' => '#ffe4e6',
                'homepage_content_background_color' => '#ffffffbf',
                'homepage_primary_color' => '#f87171',
                'homepage_primary_text_color' => '#991b1b',
                'homepage_secondary_color' => '#ef4444',
                'homepage_secondary_text_color' => '#ffd4d4',
            ],
            self::ARCTIC => [
                'name' => self::ARCTIC->value,
                'homepage_background_color' => '#71bdad',
                'homepage_content_background_color' => '#ffffffbf',
                'homepage_primary_color' => '#14b8a6',
                'homepage_primary_text_color' => '#134e4a',
                'homepage_secondary_color' => '#0d9488',
                'homepage_secondary_text_color' => '#ffffff',
            ],
            self::NOIR => [
                'name' => self::NOIR->value,
                'homepage_background_color' => '#09090b',
                'homepage_content_background_color' => '#18181bbf',
                'homepage_primary_color' => '#f87171',
                'homepage_primary_text_color' => '#fafafa',
                'homepage_secondary_color' => '#f87172ff',
                'homepage_secondary_text_color' => '#ffffff',
            ],
        };
    }

    public static function getAllThemes(): array
    {
        return array_map(
            static fn(self $theme) => $theme->getThemeData(),
            self::cases()
        );
    }
}
