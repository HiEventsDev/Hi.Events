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
    /**
     * Build context for order confirmation emails
     */
    public function buildOrderConfirmationContext(
        OrderDomainObject        $order,
        EventDomainObject        $event,
        OrganizerDomainObject    $organizer,
        EventSettingDomainObject $eventSettings
    ): array
    {
        $eventDate = new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone()));

        return [
            // Event tokens
            'event_title' => $event->getTitle(),
            'event_date' => $eventDate->format('F j, Y'),
            'event_time' => $eventDate->format('g:i A'),
            'event_location' => $eventSettings->getLocationDetails() ? AddressHelper::formatAddress($eventSettings->getLocationDetails()) : '',
            'event_description' => $event->getDescription() ?? '',

            // Order tokens
            'order_url' => sprintf(
                Url::getFrontEndUrlFromConfig(Url::ORDER_SUMMARY),
                $event->getId(),
                $order->getShortId()
            ),
            'order_number' => $order->getPublicId(),
            'order_total' => Currency::format($order->getTotalGross(), $event->getCurrency()),
            'order_date' => (new Carbon($order->getCreatedAt()))->format('F j, Y'),
            'order_first_name' => $order->getFirstName() ?? '',
            'order_last_name' => $order->getLastName() ?? '',
            'order_email' => $order->getEmail() ?? '',
            'order_is_pending' => $order->isOrderAwaitingOfflinePayment(),
            'is_offline_payment' => $order->getPaymentProvider() === PaymentProviders::OFFLINE->value,

            // Organizer tokens
            'organizer_name' => $organizer->getName() ?? '',
            'organizer_email' => $organizer->getEmail() ?? '',
            'support_email' => $eventSettings->getSupportEmail() ?? $organizer->getEmail() ?? '',

            // Additional context
            'offline_payment_instructions' => $eventSettings->getOfflinePaymentInstructions() ?? '',
            'post_checkout_message' => $eventSettings->getPostCheckoutMessage() ?? '',
        ];
    }

    /**
     * Build context for attendee ticket emails
     */
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

        return array_merge($baseContext, [
            // Attendee specific tokens
            'attendee_name' => $attendee->getFirstName() . ' ' . $attendee->getLastName(),
            'attendee_email' => $attendee->getEmail() ?? '',
            'ticket_name' => $ticketName,
            'ticket_price' => $ticketPrice,
            'ticket_url' => sprintf(
                Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET),
                $event->getId(),
                $attendee->getShortId()
            ),
        ]);
    }

    /**
     * Build preview context with sample data
     */
    public function buildPreviewContext(string $templateType): array
    {
        $baseContext = [
            'event_title' => __('Summer Music Festival 2024'),
            'event_date' => 'April 25, 2029',
            'event_time' => '7:00 PM',
            'event_location' => __('Madison Square Garden, New York'),
            'event_description' => __('Join us for an unforgettable evening of live music featuring top artists from around the world.'),
            'organizer_name' => 'ACME Events Inc.',
            'organizer_email' => 'contact@example.com',
            'support_email' => 'support@example.com',
            'order_url' => 'https://example.com/order/ABC123',
            'order_number' => IdHelper::publicId(IdHelper::ORDER_PREFIX),
            'order_total' => '$150.00',
            'order_date' => 'January 10, 2024',
            'order_first_name' => 'John',
            'order_last_name' => 'Smith',
            'order_email' => 'john@example.com',
            'order_is_pending' => false,
            'is_offline_payment' => false,
            'offline_payment_instructions' => __('Please transfer the total amount to the following bank account within 5 business days.'),
            'post_checkout_message' => __('Thank you for your purchase! We look forward to seeing you at the event.'),
        ];

        if ($templateType === 'attendee_ticket') {
            $baseContext['attendee_name'] = 'John Smith';
            $baseContext['attendee_email'] = 'john@example.com';
            $baseContext['ticket_name'] = 'VIP Pass';
            $baseContext['ticket_price'] = '$75.00';
            $baseContext['ticket_url'] = 'https://example.com/ticket/XYZ789';
        }

        return $baseContext;
    }
}
