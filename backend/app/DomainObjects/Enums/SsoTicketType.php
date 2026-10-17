<?php

namespace HiEvents\DomainObjects\Enums;

enum SsoTicketType: string
{
    use BaseEnum;

    case STATE = 'state';
    case LOGIN = 'login';
    case SIGNUP = 'signup';
    case REAUTH = 'reauth';

    public function ttlSeconds(): int
    {
        return match ($this) {
            self::STATE => 600,
            self::LOGIN, self::REAUTH => 300,
            self::SIGNUP => 1800,
        };
    }
}
