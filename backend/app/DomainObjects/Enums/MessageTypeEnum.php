<?php

namespace HiEvents\DomainObjects\Enums;

enum MessageTypeEnum
{
    use BaseEnum;

    // Emails the owner of the order
    case ORDER_OWNER;

    // Emails the attendees with a specific ticket
    case TICKET_HOLDERS;

    // Emails specific attendees
    case INDIVIDUAL_ATTENDEES;

    // Emails all attendees of the event
    case ALL_ATTENDEES;

    // Emails all customers who have purchased a specific product, ticket or merchandise etc.
    case ORDER_OWNERS_WITH_PRODUCT;
}
