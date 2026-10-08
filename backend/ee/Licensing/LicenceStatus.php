<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

enum LicenceStatus: string
{
    case ACTIVE = 'ACTIVE';
    case GRACE = 'GRACE';
    case LAPSED = 'LAPSED';
    case NONE = 'NONE';
    case DEV = 'DEV';

    public function unlocksFeatures(): bool
    {
        return in_array($this, [self::ACTIVE, self::GRACE, self::DEV], true);
    }
}
