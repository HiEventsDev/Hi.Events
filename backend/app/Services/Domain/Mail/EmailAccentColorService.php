<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\Services\Domain\Mail\DTO\EmailAccentColorDTO;

class EmailAccentColorService
{
    private const DARK_TEXT = '#111111';

    private const LIGHT_TEXT = '#ffffff';

    private const MIN_BUTTON_CONTRAST_ON_WHITE = 1.5;

    private const MIN_LINK_CONTRAST_ON_WHITE = 4.5;

    public function fromAccent(?string $accent): ?EmailAccentColorDTO
    {
        $hex = $this->normaliseHex($accent);
        if ($hex === null) {
            return null;
        }

        $luminance = $this->relativeLuminance($hex);
        $contrastOnWhite = $this->contrastRatio($luminance, 1.0);

        if ($contrastOnWhite < self::MIN_BUTTON_CONTRAST_ON_WHITE) {
            return null;
        }

        $color = '#'.$hex;

        return new EmailAccentColorDTO(
            buttonColor: $color,
            buttonTextColor: $contrastOnWhite >= $this->contrastRatio($luminance, 0.0) ? self::LIGHT_TEXT : self::DARK_TEXT,
            linkColor: $contrastOnWhite >= self::MIN_LINK_CONTRAST_ON_WHITE ? $color : null,
        );
    }

    private function normaliseHex(?string $accent): ?string
    {
        if ($accent === null || ! preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/iD', $accent, $matches)) {
            return null;
        }

        $hex = strtolower($matches[1]);

        if (strlen($hex) <= 4) {
            $hex = implode('', array_map(fn (string $digit) => $digit.$digit, str_split(substr($hex, 0, 3))));
        }

        return substr($hex, 0, 6);
    }

    private function relativeLuminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private function contrastRatio(float $first, float $second): float
    {
        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }
}
