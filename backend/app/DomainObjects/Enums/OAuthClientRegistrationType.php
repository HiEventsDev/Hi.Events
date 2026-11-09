<?php

namespace HiEvents\DomainObjects\Enums;

enum OAuthClientRegistrationType: string
{
    use BaseEnum;

    case DYNAMIC = 'DYNAMIC';
}
