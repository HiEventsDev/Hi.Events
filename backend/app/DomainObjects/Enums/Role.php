<?php

namespace HiEvents\DomainObjects\Enums;

enum Role: string
{
    use BaseEnum;

    case SUPERADMIN = 'SUPERADMIN';
    case ADMIN = 'ADMIN';
    case ORGANIZER = 'ORGANIZER';

    public static function getAssignableRoles(): array
    {
        return [
            self::ADMIN->value,
            self::ORGANIZER->value,
        ];
    }
}
