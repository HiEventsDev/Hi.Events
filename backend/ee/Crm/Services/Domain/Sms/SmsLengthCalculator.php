<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

class SmsLengthCalculator
{
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENDED = '^{}\\[~]|€';

    private const SINGLE_PART_GSM = 160;

    private const MULTI_PART_GSM = 153;

    private const SINGLE_PART_UNICODE = 70;

    private const MULTI_PART_UNICODE = 67;

    public function measure(string $text): SmsLengthDTO
    {
        $basic = mb_str_split(self::GSM_BASIC);
        $extended = mb_str_split(self::GSM_EXTENDED);
        $septets = [];

        foreach (mb_str_split($text) as $character) {
            if (in_array($character, $extended, true)) {
                $septets[] = 2;
            } elseif (in_array($character, $basic, true)) {
                $septets[] = 1;
            } else {
                return $this->unicodeLength($text);
            }
        }

        return new SmsLengthDTO(
            characters: array_sum($septets),
            parts: $this->parts($septets, self::SINGLE_PART_GSM, self::MULTI_PART_GSM),
            is_unicode: false,
        );
    }

    private function unicodeLength(string $text): SmsLengthDTO
    {
        $codeUnits = array_map(
            static fn (string $character): int => intdiv(strlen(mb_convert_encoding($character, 'UTF-16LE', 'UTF-8')), 2),
            mb_str_split($text),
        );

        return new SmsLengthDTO(
            characters: array_sum($codeUnits),
            parts: $this->parts($codeUnits, self::SINGLE_PART_UNICODE, self::MULTI_PART_UNICODE),
            is_unicode: true,
        );
    }

    /**
     * @param  int[]  $characterWidths
     */
    private function parts(array $characterWidths, int $singlePartLimit, int $multiPartLimit): int
    {
        if (array_sum($characterWidths) <= $singlePartLimit) {
            return 1;
        }

        $parts = 1;
        $spaceLeft = $multiPartLimit;

        foreach ($characterWidths as $width) {
            if ($width > $spaceLeft) {
                $parts++;
                $spaceLeft = $multiPartLimit;
            }

            $spaceLeft -= $width;
        }

        return $parts;
    }
}
