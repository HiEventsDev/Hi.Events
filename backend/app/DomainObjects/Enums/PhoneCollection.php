<?php

namespace HiEvents\DomainObjects\Enums;

enum PhoneCollection: string
{
    use BaseEnum;

    case OFF = 'OFF';
    case OPTIONAL = 'OPTIONAL';
    case REQUIRED = 'REQUIRED';

    public function isCollected(): bool
    {
        return $this !== self::OFF;
    }
}
