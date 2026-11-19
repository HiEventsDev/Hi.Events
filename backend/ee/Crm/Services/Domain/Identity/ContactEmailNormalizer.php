<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Identity;

class ContactEmailNormalizer
{
    public const MAX_LENGTH = 320;

    public const SQL_TRIMMED_CHARACTERS = "E' \\t\\r\\n'";

    private const TRIMMED_CHARACTERS = " \t\r\n";

    public function normalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($email, self::TRIMMED_CHARACTERS));

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH || ! preg_match('/^[^@\s]+@[^@\s]+$/u', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
