<?php

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Organizer\OrderSummaryForOrganizer;
use Illuminate\Support\Facades\Route;

Route::get('/mail-test', static function () {
    $orderItem = (new OrderItemDomainObject())
        ->setId(1)
        ->setQuantity(1)
        ->setPrice(100)
        ->setItemName('Test Item');

    $orderItem2 = (new OrderItemDomainObject())
        ->setId(1)
        ->setQuantity(1)
        ->setPrice(100)
        ->setItemName('Test Item');

    $order = (new OrderDomainObject())
        ->setId(2)
        ->setPublicId('123')
        ->setShortId('123')
        ->setOrderItems(collect([$orderItem, $orderItem2]));

    $organizer = (new OrganizerDomainObject())
        ->setId(1)
        ->setName('Test Organizer')
        ->setEmail('s@d.com');

    $event = (new EventDomainObject())
        ->setId(1)
        ->setTitle('Test Event')
        ->setStartDate(now())
        ->setTimeZone('UTC')
        ->setOrganizer($organizer);

    return new OrderSummaryForOrganizer($order, $event);
});
