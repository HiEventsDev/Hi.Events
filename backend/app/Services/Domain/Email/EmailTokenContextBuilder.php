<?php

namespace HiEvents\Services\Domain\Email;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
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
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Throwable;

class EmailTokenContextBuilder
{
    /**
     * Callers must hydrate `$event->event_location` (with nested `location`) and
     * `$occurrence->event_location` before invoking these builders. Reads happen
     * directly off the domain objects with no lazy-load fallback — invoking from
     * a tight loop without preloads will N+1.
     */
    public function __construct(
        private readonly LiquidTemplateRenderer $liquidTemplateRenderer,
        private readonly HtmlPurifierService $htmlPurifierService,
        private readonly EmailContextHtmlEscaper $contextHtmlEscaper,
    ) {}

    public function buildOrderConfirmationContext(
        OrderDomainObject $order,
        EventDomainObject $event,
        OrganizerDomainObject $organizer,
        EventSettingDomainObject $eventSettings,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): array {
        $startDateRaw = $occurrence?->getStartDate() ?? $event->getStartDate();
        $endDateRaw = $occurrence?->getEndDate() ?? $event->getEndDate();

        $eventStartDate = $startDateRaw ? new Carbon(DateHelper::convertFromUTC($startDateRaw, $event->getTimezone())) : null;
        $eventEndDate = $endDateRaw ? new Carbon(DateHelper::convertFromUTC($endDateRaw, $event->getTimezone())) : null;

        $eventLocation = $occurrence?->getEventLocation() ?? $event->getEventLocation();
        $structuredAddress = $this->extractStructuredAddress($eventLocation);

        $context = [
            'event' => [
                'title' => $event->getTitle().($occurrence?->getLabel() ? ' - '.$occurrence->getLabel() : ''),
                'date' => $eventStartDate?->format('F j, Y') ?? '',
                'time' => $eventStartDate?->format('g:i A') ?? '',
                'end_date' => $eventEndDate?->format('F j, Y') ?? '',
                'end_time' => $eventEndDate?->format('g:i A') ?? '',
                'full_address' => $structuredAddress ? AddressHelper::formatAddress($structuredAddress) : '',
                'location_details' => $structuredAddress,
                'description' => $event->getDescription() ?? '',
                'timezone' => $event->getTimezone(),
            ],

            'event_location' => $this->buildLocationContext($eventLocation, $structuredAddress),

            'order' => [
                'url' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ORDER_SUMMARY),
                    $event->getId(),
                    $order->getShortId()
                ),
                'number' => $order->getPublicId(),
                'total' => Currency::format($order->getTotalGross(), $event->getCurrency()),
                'date' => (new Carbon($order->getCreatedAt()))->format('F j, Y'),
                'currency' => $order->getCurrency(),
                'locale' => $order->getLocale(),
                'first_name' => $order->getFirstName() ?? '',
                'last_name' => $order->getLastName() ?? '',
                'email' => $order->getEmail() ?? '',
                'is_awaiting_offline_payment' => $order->isOrderAwaitingOfflinePayment(),
                'is_offline_payment' => $order->getPaymentProvider() === PaymentProviders::OFFLINE->value,
            ],

            'organizer' => [
                'name' => $organizer->getName() ?? '',
                'email' => $organizer->getEmail() ?? '',
            ],

            'settings' => [
                'support_email' => $eventSettings->getSupportEmail() ?? $organizer->getEmail() ?? '',
                'offline_payment_instructions' => $eventSettings->getOfflinePaymentInstructions() ?? '',
                'post_checkout_message' => $eventSettings->getPostCheckoutMessage() ?? '',
            ],

            'occurrence' => [
                'start_date' => $eventStartDate?->format('F j, Y') ?? '',
                'start_time' => $eventStartDate?->format('g:i A') ?? '',
                'end_date' => $eventEndDate?->format('F j, Y') ?? '',
                'end_time' => $eventEndDate?->format('g:i A') ?? '',
                'label' => $occurrence?->getLabel() ?? '',
            ],
        ];

        $context['settings']['offline_payment_instructions'] = $this->renderOfflinePaymentInstructions($context);

        return $context;
    }

    private function renderOfflinePaymentInstructions(array $context): string
    {
        $instructions = $context['settings']['offline_payment_instructions'];

        if ($instructions === '') {
            return $instructions;
        }

        try {
            $rendered = $this->liquidTemplateRenderer->render($instructions, $this->contextHtmlEscaper->escape($context));

            return $this->htmlPurifierService->purify($rendered) ?? $instructions;
        } catch (Throwable) {
            return $instructions;
        }
    }

    public function buildAttendeeTicketContext(
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        OrganizerDomainObject $organizer,
        EventSettingDomainObject $eventSettings,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): array {
        $baseContext = $this->buildOrderConfirmationContext($order, $event, $organizer, $eventSettings, $occurrence);

        /** @var OrderItemDomainObject $orderItem */
        $orderItem = $order->getOrderItems()->first(fn (OrderItemDomainObject $item) => $item->getProductPriceId() === $attendee->getProductPriceId());

        $ticketPrice = Currency::format($orderItem?->getPrice() ?? 0, $event->getCurrency());
        $ticketName = $orderItem?->getItemName();

        $baseContext['attendee'] = [
            'name' => $attendee->getFirstName().' '.$attendee->getLastName(),
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

    public function buildOccurrenceCancellationContext(
        EventDomainObject $event,
        EventOccurrenceDomainObject $occurrence,
        OrganizerDomainObject $organizer,
        EventSettingDomainObject $eventSettings,
        bool $refundOrders = false,
    ): array {
        $startDateRaw = $occurrence->getStartDate();
        $endDateRaw = $occurrence->getEndDate();

        $eventStartDate = $startDateRaw ? new Carbon(DateHelper::convertFromUTC($startDateRaw, $event->getTimezone())) : null;
        $eventEndDate = $endDateRaw ? new Carbon(DateHelper::convertFromUTC($endDateRaw, $event->getTimezone())) : null;

        $eventLocation = $occurrence->getEventLocation() ?? $event->getEventLocation();
        $structuredAddress = $this->extractStructuredAddress($eventLocation);

        return [
            'event' => [
                'title' => $event->getTitle().($occurrence->getLabel() ? ' - '.$occurrence->getLabel() : ''),
                'date' => $eventStartDate?->format('F j, Y') ?? '',
                'time' => $eventStartDate?->format('g:i A') ?? '',
                'end_date' => $eventEndDate?->format('F j, Y') ?? '',
                'end_time' => $eventEndDate?->format('g:i A') ?? '',
                'full_address' => $structuredAddress ? AddressHelper::formatAddress($structuredAddress) : '',
                'location_details' => $structuredAddress,
                'description' => $event->getDescription() ?? '',
                'timezone' => $event->getTimezone(),
                'url' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::EVENT_HOMEPAGE),
                    $event->getId(),
                    $event->getSlug(),
                ),
            ],

            'event_location' => $this->buildLocationContext($eventLocation, $structuredAddress),

            'occurrence' => [
                'start_date' => $eventStartDate?->format('F j, Y') ?? '',
                'start_time' => $eventStartDate?->format('g:i A') ?? '',
                'end_date' => $eventEndDate?->format('F j, Y') ?? '',
                'end_time' => $eventEndDate?->format('g:i A') ?? '',
                'label' => $occurrence->getLabel() ?? '',
            ],

            'organizer' => [
                'name' => $organizer->getName() ?? '',
                'email' => $organizer->getEmail() ?? '',
            ],

            'settings' => [
                'support_email' => $eventSettings->getSupportEmail() ?? $organizer->getEmail() ?? '',
            ],

            'cancellation' => [
                'refund_issued' => $refundOrders,
            ],
        ];
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
                ],
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
                'currency' => 'USD',
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

        $baseContext['occurrence'] = [
            'start_date' => 'April 25, 2029',
            'start_time' => '7:00 PM',
            'end_date' => 'April 26, 2029',
            'end_time' => '11:00 PM',
            'label' => 'Session A',
        ];

        $baseContext['event_location'] = [
            'type' => LocationType::IN_PERSON->name,
            'is_online' => false,
            'online_connection_details' => null,
            'name' => '3 Arena',
            'label' => null,
            'formatted_address' => __('3 Arena, North Wall Quay, Dublin 1, Ireland'),
            'latitude' => 53.3478,
            'longitude' => -6.2289,
            'structured_address' => $baseContext['event']['location_details'],
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

        if ($templateType === 'occurrence_cancellation') {
            $baseContext['cancellation'] = [
                'refund_issued' => true,
            ];
            $baseContext['event']['url'] = 'https://example.com/event/123/summer-fest';
        }

        return $baseContext;
    }

    private function extractStructuredAddress(?EventLocationDomainObject $eventLocation): ?array
    {
        if ($eventLocation === null) {
            return null;
        }

        if ($eventLocation->getType() !== LocationType::IN_PERSON->name) {
            return null;
        }

        $location = $eventLocation->getLocation();
        if ($location === null) {
            return null;
        }

        $address = $location->getStructuredAddress();

        return is_array($address) ? $address : null;
    }

    private function buildLocationContext(?EventLocationDomainObject $eventLocation, ?array $structuredAddress): array
    {
        $type = $eventLocation?->getType();
        $location = $eventLocation?->getLocation();

        return [
            'type' => $type,
            'is_online' => $type === LocationType::ONLINE->name,
            'online_connection_details' => $eventLocation?->getOnlineEventConnectionDetails(),
            'name' => $location?->getName(),
            'formatted_address' => $structuredAddress ? AddressHelper::formatAddress($structuredAddress) : '',
            'latitude' => $location?->getLatitude(),
            'longitude' => $location?->getLongitude(),
            'structured_address' => $structuredAddress,
        ];
    }
}
