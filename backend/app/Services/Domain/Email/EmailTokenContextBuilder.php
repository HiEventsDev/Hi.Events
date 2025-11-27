<?php

namespace HiEvents\Services\Domain\Email;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\AddressHelper;
use HiEvents\Helper\Currency;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Helper\Url;
use HiEvents\Locale;

class EmailTokenContextBuilder
{
    public function buildOrderConfirmationContext(
        OrderDomainObject        $order,
        EventDomainObject        $event,
        OrganizerDomainObject    $organizer,
        EventSettingDomainObject $eventSettings
    ): array
    {
        $eventStartDate = new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone()));
        $eventEndDate = $event->getEndDate() ? new Carbon(DateHelper::convertFromUTC($event->getEndDate(), $event->getTimezone())) : null;

        return [
            // Event object
            'event' => [
                'title' => $event->getTitle(),
                'date' => $eventStartDate->format('F j, Y'),
                'time' => $eventStartDate->format('g:i A'),
                'end_date' => $eventEndDate?->format('F j, Y') ?? '',
                'end_time' => $eventEndDate?->format('g:i A') ?? '',
                'full_address' => $eventSettings->getLocationDetails() ? AddressHelper::formatAddress($eventSettings->getLocationDetails()) : '',
                'location_details' => $eventSettings->getLocationDetails(),
                'description' => $event->getDescription() ?? '',
                'timezone' => $event->getTimezone(),
            ],

            // Order object
            'order' => [
                'url' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ORDER_SUMMARY),
                    $event->getId(),
                    $order->getShortId()
                ),
                'number' => $order->getPublicId(),
                'total' => Currency::format($order->getTotalGross(), $event->getCurrency()),
                'date' => (new Carbon($order->getCreatedAt()))->format('F j, Y'),
                'currency' => $order->getCurrency(), // added
                'locale' => $order->getLocale(), // added
                'first_name' => $order->getFirstName() ?? '',
                'last_name' => $order->getLastName() ?? '',
                'email' => $order->getEmail() ?? '',
                'is_awaiting_offline_payment' => $order->isOrderAwaitingOfflinePayment(),
                'is_offline_payment' => $order->getPaymentProvider() === PaymentProviders::OFFLINE->value,
            ],

            // Organizer object
            'organizer' => [
                'name' => $organizer->getName() ?? '',
                'email' => $organizer->getEmail() ?? '',
            ],

            // Settings object
            'settings' => [
                'support_email' => $eventSettings->getSupportEmail() ?? $organizer->getEmail() ?? '',
                'offline_payment_instructions' => $eventSettings->getOfflinePaymentInstructions() ?? '',
                'post_checkout_message' => $eventSettings->getPostCheckoutMessage() ?? '',
            ],
        ];
    }

    public function buildAttendeeTicketContext(
        AttendeeDomainObject     $attendee,
        OrderDomainObject        $order,
        EventDomainObject        $event,
        OrganizerDomainObject    $organizer,
        EventSettingDomainObject $eventSettings
    ): array
    {
        $baseContext = $this->buildOrderConfirmationContext($order, $event, $organizer, $eventSettings);

        /** @var OrderItemDomainObject $orderItem */
        $orderItem = $order->getOrderItems()->first(fn(OrderItemDomainObject $item) => $item->getProductPriceId() === $attendee->getProductPriceId());

        $ticketPrice = Currency::format($orderItem?->getPrice(), $event->getCurrency());
        $ticketName = $orderItem->getItemName();

        // Add attendee and ticket objects
        $baseContext['attendee'] = [
            'name' => $attendee->getFirstName() . ' ' . $attendee->getLastName(),
            'email' => $attendee->getEmail() ?? '',
        ];

        $baseContext['ticket'] = [
            'name' => $ticketName,
            'price' => $ticketPrice,
            'url' => sprintf(
                Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET),
                $event->getId(),
                $attendee->getShortId()
            ),
        ];

        return $baseContext;
    }

    public function buildPreviewContext(string $templateType): array
    {
        $baseContext = [
            'event' => [
                'title' => __('Summer Music Festival 2024'),
                'date' => 'April 25, 2029',
                'time' => '7:00 PM',
                'end_date' => 'April 26, 2029',
                'end_time' => '11:00 PM',
                'full_address' => __('3 Arena, North Wall Quay, Dublin 1, Ireland'),
                'description' => __('Join us for an unforgettable evening of live music featuring top artists from around the world.'),
                'timezone' => 'UTC',
                'location_details' => [
                    'venue_name' => '3 Arena',
                    'address_line_1' => 'North Wall Quay',
                    'address_line_2' => '',
                    'city' => 'Dublin',
                    'state_or_region' => 'Dublin 1',
                    'zip_or_postal_code' => 'D01 T0X4',
                    'country' => 'IE',
                ]
            ],
            'order' => [
                'url' => 'https://example.com/order/ABC123',
                'number' => IdHelper::publicId(IdHelper::ORDER_PREFIX),
                'total' => '$150.00',
                'date' => 'January 10, 2024',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john@example.com',
                'is_awaiting_offline_payment' => false,
                'is_offline_payment' => false,
                'locale' => Locale::EN->value,
                'currency' => 'USD'
            ],
            'organizer' => [
                'name' => 'ACME Events Inc.',
                'email' => 'contact@example.com',
            ],
            'settings' => [
                'support_email' => 'support@example.com',
                'offline_payment_instructions' => __('Please transfer the total amount to the following bank account within 5 business days.'),
                'post_checkout_message' => __('Thank you for your purchase! We look forward to seeing you at the event.'),
            ],
        ];

        if ($templateType === 'attendee_ticket') {
            $baseContext['attendee'] = [
                'name' => 'John Smith',
                'email' => 'john@example.com',
            ];
            $baseContext['ticket'] = [
                'name' => 'VIP Pass',
                'price' => '$75.00',
                'url' => 'https://example.com/ticket/XYZ789',
            ];
        }

        return $baseContext;
    }
}
