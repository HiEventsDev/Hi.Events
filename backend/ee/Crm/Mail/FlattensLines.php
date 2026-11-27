<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

use HiEvents\Helper\StringHelper;

trait FlattensLines
{
    private function onOneLine(string $text): string
    {
        return StringHelper::onOneLine($text);
    }
}
