<?php

namespace HiEvents\DomainObjects\Enums;

enum Permission: string
{
    use BaseEnum;

    case EDIT = 'EDIT';
    case VIEW_FINANCIALS = 'VIEW_FINANCIALS';
    case ISSUE_REFUNDS = 'ISSUE_REFUNDS';
    case MANAGE_PAYMENTS = 'MANAGE_PAYMENTS';
    case DELETE_EVENTS = 'DELETE_EVENTS';
    case MANAGE_TEAM = 'MANAGE_TEAM';
    case MANAGE_ACCOUNT = 'MANAGE_ACCOUNT';

    /**
     * @return Permission[]
     */
    public static function adjustable(): array
    {
        return [
            self::VIEW_FINANCIALS,
            self::ISSUE_REFUNDS,
        ];
    }

    /**
     * @return Permission[]
     */
    public function requires(): array
    {
        return match ($this) {
            self::ISSUE_REFUNDS => [self::EDIT, self::VIEW_FINANCIALS],
            self::MANAGE_PAYMENTS => [self::VIEW_FINANCIALS],
            default => [],
        };
    }
}
