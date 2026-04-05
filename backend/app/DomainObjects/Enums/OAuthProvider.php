<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Enums;

enum OAuthProvider: string
{
    case GOOGLE = 'google';
    case APPLE = 'apple';
}
