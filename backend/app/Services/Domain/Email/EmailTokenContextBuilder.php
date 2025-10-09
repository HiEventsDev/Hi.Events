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

class EmailTokenContextBuilder
{
    public function buildOrderConfirmationContext(
        OrderDomainObject        $order,
        EventDomainObject        $event,
        OrganizerDomainObject    $organizer,
        EventSettingDomainObject $eventSettings
    ): array
    {
        $eventDate = new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone()));

        return [
            // Event object
            'event' => [
                'title' => $event->getTitle(),
                'date' => $eventDate->format('F j, Y'),
                'time' => $eventDate->format('g:i A'),
                'location' => $eventSettings->getLocationDetails() ? AddressHelper::formatAddress($eventSettings->getLocationDetails()) : '',
                'description' => $event->getDescription() ?? '',
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
                'first_name' => $order->getFirstName() ?? '',
                'last_name' => $order->getLastName() ?? '',
                'email' => $order->getEmail() ?? '',
                'is_pending' => $order->isOrderAwaitingOfflinePayment(),
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

            // Top-level flags (for backward compatibility and convenience)
            'is_offline_payment' => $order->getPaymentProvider() === PaymentProviders::OFFLINE->value,
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
                'location' => __('Madison Square Garden, New York'),
                'description' => __('Join us for an unforgettable evening of live music featuring top artists from around the world.'),
            ],
            'order' => [
                'url' => 'https://example.com/order/ABC123',
                'number' => IdHelper::publicId(IdHelper::ORDER_PREFIX),
                'total' => '$150.00',
                'date' => 'January 10, 2024',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john@example.com',
                'is_pending' => false,
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
            'is_offline_payment' => false,
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
