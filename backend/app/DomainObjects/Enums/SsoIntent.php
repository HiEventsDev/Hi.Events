<?php

namespace HiEvents\DomainObjects\Enums;

enum SsoIntent: string
{
    use BaseEnum;

    case LOGIN = 'LOGIN';
    case SIGNUP = 'SIGNUP';
    case INVITE = 'INVITE';
    case LINK = 'LINK';
    case REAUTH = 'REAUTH';
    case TEST = 'TEST';

    /**
     * @return self[]
     */
    public static function accountConnectionIntents(): array
    {
        return [self::LOGIN, self::REAUTH, self::TEST];
    }
}
