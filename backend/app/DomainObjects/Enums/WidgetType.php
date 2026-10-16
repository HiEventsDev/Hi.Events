<?php

namespace HiEvents\DomainObjects\Enums;

enum WidgetType: string
{
    use BaseEnum;

    case TICKETS = 'TICKETS';
    case BUTTON = 'BUTTON';
    case LINEUP = 'LINEUP';
    case SCHEDULE = 'SCHEDULE';
    case EVENTS = 'EVENTS';

    /**
     * @return string[]
     */
    public static function eventTypes(): array
    {
        return [
            self::TICKETS->value,
            self::BUTTON->value,
            self::LINEUP->value,
            self::SCHEDULE->value,
        ];
    }

    /**
     * @return string[]
     */
    public static function organizerTypes(): array
    {
        return [self::EVENTS->value];
    }
}
