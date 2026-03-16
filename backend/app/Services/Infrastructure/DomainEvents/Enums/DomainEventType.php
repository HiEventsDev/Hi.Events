<?php

namespace HiEvents\Services\Infrastructure\DomainEvents\Enums;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum DomainEventType: string
{
    use BaseEnum;

    case PRODUCT_CREATED = 'product.created';
    case PRODUCT_UPDATED = 'product.updated';
    case PRODUCT_DELETED = 'product.deleted';

    case EVENT_CREATED = 'event.created';
    case EVENT_UPDATED = 'event.updated';
    case EVENT_ARCHIVED = 'event.archived';

    case ORDER_CREATED = 'order.created';
    case ORDER_UPDATED = 'order.updated';
    case ORDER_MARKED_AS_PAID = 'order.marked_as_paid';
    case ORDER_REFUNDED = 'order.refunded';
    case ORDER_CANCELLED = 'order.cancelled';

    case ATTENDEE_CREATED = 'attendee.created';
    case ATTENDEE_UPDATED = 'attendee.updated';
    case ATTENDEE_CANCELLED = 'attendee.cancelled';

    case CHECKIN_CREATED = 'checkin.created';
    case CHECKIN_DELETED = 'checkin.deleted';
}
