<?php

namespace HiEvents\Services\Domain\Email;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;

class EmailTemplateService
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
        private readonly LiquidTemplateRenderer $liquidRenderer,
        private readonly EmailTokenContextBuilder $tokenBuilder
    ) {
    }

    /**
     * Get a template for the given type and scope
     */
    public function getTemplate(
        EmailTemplateType $type,
        int $accountId,
        ?int $eventId = null,
        ?int $organizerId = null
    ): ?EmailTemplateDomainObject {
        return $this->emailTemplateRepository->findByTypeWithFallback(
            $type,
            $accountId,
            $eventId,
            $organizerId
        );
    }

    /**
     * Render a template with the given context
     */
    public function renderTemplate(EmailTemplateDomainObject $template, array $context): RenderedEmailTemplateDTO
    {
        $renderedSubject = $this->liquidRenderer->render($template->getSubject(), $context);
        $renderedBody = $this->liquidRenderer->render($template->getBody(), $context);

        $cta = null;

        // Handle CTA if present
        if ($template->getCta()) {
            $templateCta = $template->getCta();
            if (isset($templateCta['label'], $templateCta['url_token'])) {
                // Replace the URL token with actual value from context
                $ctaUrl = $context[$templateCta['url_token']] ?? '#';
                $cta = [
                    'label' => $templateCta['label'],
                    'url' => $ctaUrl,
                ];
            }
        }

        return new RenderedEmailTemplateDTO(
            subject: $renderedSubject,
            body: $renderedBody,
            cta: $cta,
        );
    }

    /**
     * Get default template content
     */
    public function getDefaultTemplate(EmailTemplateType $type): array
    {
        $defaults = $this->getDefaultTemplates();
        $ctaDefaults = $this->getDefaultCTAs();

        $template = $defaults[$type->value] ?? [
            'subject' => 'Email from {{ organizer_name }}',
            'body' => 'Hello {{ attendee_name }},\n\nThank you for your order.',
        ];

        $template['cta'] = $ctaDefaults[$type->value] ?? null;

        return $template;
    }

    /**
     * Preview a template with sample data
     */
    public function previewTemplate(string $subject, string $body, EmailTemplateType $type): array
    {
        $context = $this->tokenBuilder->buildPreviewContext($type->value);

        return [
            'subject' => $this->liquidRenderer->render($subject, $context),
            'body' => $this->liquidRenderer->render($body, $context),
            'context' => $context, // Return context for debugging
        ];
    }

    /**
     * Validate template syntax
     */
    public function validateTemplate(string $subject, string $body): array
    {
        $errors = [];

        $subjectError = $this->liquidRenderer->getValidationErrors($subject);
        if ($subjectError) {
            $errors['subject'] = $subjectError;
        }

        $bodyError = $this->liquidRenderer->getValidationErrors($body);
        if ($bodyError) {
            $errors['body'] = $bodyError;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get default CTAs for each template type
     */
    private function getDefaultCTAs(): array
    {
        return [
            EmailTemplateType::ORDER_CONFIRMATION->value => [
                'label' => 'View Order',
                'url_token' => 'order_url',
            ],
            EmailTemplateType::ATTENDEE_TICKET->value => [
                'label' => 'View Ticket',
                'url_token' => 'ticket_url',
            ],
        ];
    }

    /**
     * Get default templates for each type
     */
    private function getDefaultTemplates(): array
    {
        return [
            EmailTemplateType::ORDER_CONFIRMATION->value => [
                'subject' => 'Your Order is Confirmed! 🎉',
                'body' => <<<'LIQUID'
<strong>Your Order is Confirmed! 🎉</strong><br>

{% if order_is_pending %}
<strong>ℹ️ Payment Pending:</strong> Your order is pending payment. Tickets have been issued but will not be valid until payment is received.<br>
<strong>Payment Instructions</strong><br>
Please follow the instructions below to complete your payment:<br>
{% if offline_payment_instructions %}
{{ offline_payment_instructions }}<br>
{% endif %}

{% else %}
Congratulations! Your order for <strong>{{ event_title }}</strong> on <strong>{{ event_date }}</strong> at <strong>{{ event_time }}</strong> was successful. Please find your order details below.<br>
{% endif %}

<strong>Event Details</strong><br>
<strong>Event Name:</strong> {{ event_title }}<br>
<strong>Date & Time:</strong> {{ event_date }} at {{ event_time }}<br>
{% if event_location %}<strong>Location:</strong> {{ event_location }}<br>{% endif %}
<br>

{% if post_checkout_message %}
<strong>Additional Information</strong><br>
{{ post_checkout_message }}<br>
{% endif %}

<strong>Order Summary</strong><br>
<strong>Order Number:</strong> {{ order_number }}<br>
<strong>Total Amount:</strong> {{ order_total }}<br>

If you have any questions or need assistance, please contact <a href="mailto:{{ support_email }}">{{ support_email }}</a>.<br>

Best regards,<br>
{{ organizer_name }}
LIQUID
            ],
            EmailTemplateType::ATTENDEE_TICKET->value => [
                'subject' => '🎟️ Your Ticket for {{ event_title }}',
                'body' => <<<'LIQUID'
<strong>You're going to {{ event_title }}! 🎉</strong><br>

{% if order_is_pending %}
<strong>ℹ️ Payment Pending:</strong> Your order is pending payment. Tickets have been issued but will not be valid until payment is received.<br>
{% endif %}

Hi {{ attendee_name }},<br>

Please find your ticket details below.<br>

<strong>Event Information</strong><br>
<strong>Event:</strong> {{ event_title }}<br>
<strong>Date:</strong> {{ event_date }}<br>
<strong>Time:</strong> {{ event_time }}<br>
{% if event_location %}<strong>Location:</strong> {{ event_location }}<br>{% endif %}
<br>

<strong>Your Ticket</strong><br>
<strong>Ticket Type:</strong> {{ ticket_name }}<br>
<strong>Price:</strong> {{ ticket_price }}<br>
<strong>Attendee:</strong> {{ attendee_name }}<br>

<strong>💡 Remember:</strong> Please have your ticket ready when you arrive at the event.<br>

If you have any questions or need assistance, please reply to this email or contact the event organizer at <a href="mailto:{{ support_email }}">{{ support_email }}</a>.<br>

Best regards,<br>
{{ organizer_name }}
LIQUID
            ],
        ];
    }
}
