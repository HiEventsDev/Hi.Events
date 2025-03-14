<?php

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Helper\IdHelper;
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
        ->setFirstName('Test')
        ->setLastName('User')
        ->setEmail('test@test.com')
        ->setId(2)
        ->setPublicId(IdHelper::publicId('o'))
        ->setShortId('123')
        ->setStatus(OrderStatus::COMPLETED->name)
        ->setOrderItems(collect([$orderItem, $orderItem2]))
        ->setTotalGross(200);

    $organizer = (new OrganizerDomainObject())
        ->setId(1)
        ->setName('Test Organizer')
        ->setEmail('s@d.com');

    $eventSettings = (new EventSettingDomainObject())
        ->setSupportEmail('d@d.com')
        ->setPostCheckoutMessage('Thank you for your order');

    $event = (new EventDomainObject())
        ->setId(1)
        ->setTitle('Test Event')
        ->setStartDate(now())
        ->setTimeZone('UTC')
        ->setOrganizer($organizer)
        ->setEventSettings($eventSettings);

    return new \HiEvents\Mail\Organizer\OrderSummaryForOrganizer(
        order: $order,
        event: $event,
    );
});
