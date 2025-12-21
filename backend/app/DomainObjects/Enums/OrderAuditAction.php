<?php

namespace HiEvents\DomainObjects\Enums;

enum OrderAuditAction: string
{
    use BaseEnum;

    case ATTENDEE_UPDATED = 'ATTENDEE_UPDATED';
    case ORDER_UPDATED = 'ORDER_UPDATED';
    case ATTENDEE_EMAIL_RESENT = 'ATTENDEE_EMAIL_RESENT';
    case ORDER_EMAIL_RESENT = 'ORDER_EMAIL_RESENT';
}
