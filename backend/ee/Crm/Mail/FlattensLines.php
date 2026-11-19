<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

trait FlattensLines
{
    private function onOneLine(string $text): string
    {
        return trim((string) preg_replace('/\s*\R\s*/u', ' ', $text));
    }
}
