<?php

namespace HiEvents\Services\Infrastructure\Email;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use Liquid\Exception\ParseException;
use Liquid\Template;

class LiquidTemplateRenderer
{
    private Template $liquid;

    public function __construct()
    {
        $this->liquid = new Template();
        $this->liquid->parse(''); // Initialize
    }

    /**
     * Render a Liquid template with the given context
     */
    public function render(string $template, array $context): string
    {
        try {
            $this->liquid->parse($template);
            return $this->liquid->render($context);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate a Liquid template syntax
     */
    public function validate(string $template): bool
    {
        try {
            $this->liquid->parse($template);
            return true;
        } catch (ParseException $e) {
            return false;
        }
    }

    /**
     * Get validation errors for a template
     */
    public function getValidationErrors(string $template): ?string
    {
        try {
            $this->liquid->parse($template);
            return null;
        } catch (ParseException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Get available tokens for a specific template type
     */
    public function getAvailableTokens(EmailTemplateType $type): array
    {
        $commonTokens = [
            [
                'token' => '{{ event_title }}',
                'description' => __('The name of the event'),
                'example' => 'Summer Music Festival 2024',
            ],
            [
                'token' => '{{ event_date }}',
                'description' => __('The event date'),
                'example' => 'January 15, 2024',
            ],
            [
                'token' => '{{ event_time }}',
                'description' => __('The event time'),
                'example' => '7:00 PM',
            ],
            [
                'token' => '{{ event_location }}',
                'description' => __('The event location'),
                'example' => 'Madison Square Garden',
            ],
            [
                'token' => '{{ organizer_name }}',
                'description' => __('The organizer\'s name'),
                'example' => 'ACME Events Inc.',
            ],
            [
                'token' => '{{ organizer_email }}',
                'description' => __('The organizer\'s email'),
                'example' => 'contact@acme-events.com',
            ],
            [
                'token' => '{{ support_email }}',
                'description' => __('The support email address'),
                'example' => 'support@acme-events.com',
            ],
        ];

        $orderTokens = [
            [
                'token' => '{{ order_url }}',
                'description' => __('Link to view the order summary'),
                'example' => 'https://example.com/order/ABC123',
            ],
            [
                'token' => '{{ order_number }}',
                'description' => __('The order reference number'),
                'example' => 'ORD-2024-001234',
            ],
            [
                'token' => '{{ order_total }}',
                'description' => __('The total order amount'),
                'example' => '$150.00',
            ],
            [
                'token' => '{{ order_date }}',
                'description' => __('The order date'),
                'example' => 'January 10, 2024',
            ],
            [
                'token' => '{{ order_first_name }}',
                'description' => __('The first name of the person who placed the order'),
                'example' => 'John',
            ],
            [
                'token' => '{{ order_last_name }}',
                'description' => __('The last name of the person who placed the order'),
                'example' => 'Smith',
            ],
            [
                'token' => '{% if order_is_pending %}',
                'description' => __('Conditional: Check if order is pending payment'),
                'example' => '{% if order_is_pending %}Payment pending{% endif %}',
            ],
        ];

        $attendeeTokens = [
            [
                'token' => '{{ attendee_name }}',
                'description' => __('The attendee\'s full name'),
                'example' => 'John Smith',
            ],
            [
                'token' => '{{ attendee_email }}',
                'description' => __('The attendee\'s email'),
                'example' => 'john@example.com',
            ],
            [
                'token' => '{{ ticket_name }}',
                'description' => __('The ticket type name'),
                'example' => 'VIP Pass',
            ],
            [
                'token' => '{{ ticket_url }}',
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