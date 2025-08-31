<?php

namespace HiEvents\Services\Infrastructure\Email;

use Exception;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use Liquid\Exception\ParseException;
use Liquid\Template;
use RuntimeException;

class LiquidTemplateRenderer
{
    private Template $liquid;

    public function __construct()
    {
        $this->liquid = new Template();
        $this->liquid->parse(''); // Initialize
    }

    public function render(string $template, array $context): string
    {
        try {
            $this->liquid->parse($template);
            return $this->liquid->render($context);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to render template: ' . $e->getMessage(), 0, $e);
        }
    }

    public function validate(string $template): bool
    {
        try {
            $this->liquid->parse($template);
            return true;
        } catch (ParseException $e) {
            return false;
        }
    }

    public function getValidationErrors(string $template): ?string
    {
        try {
            $this->liquid->parse($template);
            return null;
        } catch (ParseException $e) {
            return $e->getMessage();
        }
    }

    public function getAvailableTokens(EmailTemplateType $type): array
    {
        $commonTokens = [
            [
                'token' => '{{ event.title }}',
                'description' => __('The name of the event'),
                'example' => 'Summer Music Festival 2024',
            ],
            [
                'token' => '{{ event.date }}',
                'description' => __('The event date'),
                'example' => 'January 15, 2024',
            ],
            [
                'token' => '{{ event.time }}',
                'description' => __('The event time'),
                'example' => '7:00 PM',
            ],
            [
                'token' => '{{ event.location }}',
                'description' => __('The event location'),
                'example' => 'Madison Square Garden',
            ],
            [
                'token' => '{{ event.description }}',
                'description' => __('The event description'),
                'example' => 'Join us for an amazing event!',
            ],
            [
                'token' => '{{ organizer.name }}',
                'description' => __('The organizer\'s name'),
                'example' => 'ACME Events Inc.',
            ],
            [
                'token' => '{{ organizer.email }}',
                'description' => __('The organizer\'s email'),
                'example' => 'contact@acme-events.com',
            ],
            [
                'token' => '{{ settings.support_email }}',
                'description' => __('The support email address'),
                'example' => 'support@acme-events.com',
            ],
            [
                'token' => '{{ settings.offline_payment_instructions }}',
                'description' => __('Instructions for offline payment'),
                'example' => 'Please transfer payment to account...',
            ],
            [
                'token' => '{{ settings.post_checkout_message }}',
                'description' => __('Message shown after checkout'),
                'example' => 'Thank you for your purchase!',
            ],
        ];

        $orderTokens = [
            [
                'token' => '{{ order.url }}',
                'description' => __('Link to view the order summary'),
                'example' => 'https://example.com/order/ABC123',
            ],
            [
                'token' => '{{ order.number }}',
                'description' => __('The order reference number'),
                'example' => 'ORD-2024-001234',
            ],
            [
                'token' => '{{ order.total }}',
                'description' => __('The total order amount'),
                'example' => '$150.00',
            ],
            [
                'token' => '{{ order.date }}',
                'description' => __('The order date'),
                'example' => 'January 10, 2024',
            ],
            [
                'token' => '{{ order.first_name }}',
                'description' => __('The first name of the person who placed the order'),
                'example' => 'John',
            ],
            [
                'token' => '{{ order.last_name }}',
                'description' => __('The last name of the person who placed the order'),
                'example' => 'Smith',
            ],
            [
                'token' => '{{ order.email }}',
                'description' => __('The email of the person who placed the order'),
                'example' => 'john@example.com',
            ],
            [
                'token' => '{% if order.is_pending %}',
                'description' => __('Conditional: Check if order is pending payment'),
                'example' => '{% if order.is_pending %}Payment pending{% endif %}',
            ],
            [
                'token' => '{% if is_offline_payment %}',
                'description' => __('Conditional: Check if payment is offline'),
                'example' => '{% if is_offline_payment %}Offline payment{% endif %}',
            ],
        ];

        $attendeeTokens = [
            [
                'token' => '{{ attendee.name }}',
                'description' => __('The attendee\'s full name'),
                'example' => 'John Smith',
            ],
            [
                'token' => '{{ attendee.email }}',
                'description' => __('The attendee\'s email'),
                'example' => 'john@example.com',
            ],
            [
                'token' => '{{ ticket.name }}',
                'description' => __('The ticket type name'),
                'example' => 'VIP Pass',
            ],
            [
                'token' => '{{ ticket.price }}',
                'description' => __('The ticket price'),
                'example' => '$75.00',
            ],
            [
                'token' => '{{ ticket.url }}',
                'description' => __('Link to view/download the ticket'),
                'example' => 'https://example.com/ticket/XYZ789',
            ],
        ];

        return match ($type) {
            EmailTemplateType::ORDER_CONFIRMATION => array_merge($commonTokens, $orderTokens),
            EmailTemplateType::ATTENDEE_TICKET => array_merge($commonTokens, $orderTokens, $attendeeTokens),
        };
    }
}
