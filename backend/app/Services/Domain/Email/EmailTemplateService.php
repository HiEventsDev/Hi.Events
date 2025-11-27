<?php

namespace HiEvents\Services\Domain\Email;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EmailTemplateService
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
        private readonly LiquidTemplateRenderer $liquidRenderer,
        private readonly EmailTokenContextBuilder $tokenBuilder
    ) {
    }

    public function getTemplateByType(
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
                // Handle dot notation (e.g., 'order.url' -> $context['order']['url'])
                $ctaUrl = $this->getValueFromDotNotation($context, $templateCta['url_token']) ?? '#';
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

        $template = $defaults[$type->value] ?? throw new ResourceNotFoundException('No default template for type ' . $type->value);

        $template['cta'] = $ctaDefaults[$type->value] ?? null;

        return $template;
    }

    public function previewTemplate(string $subject, string $body, EmailTemplateType $type, ?array $cta = null): array
    {
        $context = $this->tokenBuilder->buildPreviewContext($type->value);

        $renderedBody = $this->liquidRenderer->render($body, $context);

        // Add CTA button if provided
        if ($cta && isset($cta['label'])) {
            $ctaUrl = $this->getValueFromDotNotation($context, $cta['url_token'] ?? '') ?? '#';
            $ctaHtml = sprintf(
                '<div style="text-align: center; margin: 30px 0;">
                    <a href="%s" style="display: inline-block; padding: 12px 30px; background-color: #213850; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">%s</a>
                </div>',
                htmlspecialchars($ctaUrl),
                htmlspecialchars($cta['label'])
            );
            $renderedBody .= $ctaHtml;
        }

        return [
            'subject' => $this->liquidRenderer->render($subject, $context),
            'body' => $renderedBody,
            'context' => $context, // Return context for debugging
        ];
    }

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
     * Get value from array using dot notation
     * e.g., 'order.url' will get $array['order']['url']
     */
    private function getValueFromDotNotation(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    private function getDefaultCTAs(): array
    {
        return [
            EmailTemplateType::ORDER_CONFIRMATION->value => [
                'label' => __('View Order & Tickets'),
                'url_token' => 'order.url',
            ],
            EmailTemplateType::ATTENDEE_TICKET->value => [
                'label' => __('View Ticket'),
                'url_token' => 'ticket.url',
            ],
        ];
    }

    private function getDefaultTemplates(): array
    {
        return [
            EmailTemplateType::ORDER_CONFIRMATION->value => [
                'subject' => 'Your Order is Confirmed! 🎉',
                'body' => <<<'LIQUID'
<strong>Your Order is Confirmed! 🎉</strong><br>

{% if order.is_awaiting_offline_payment %}
<strong>ℹ️ Payment Pending:</strong> Your order is pending payment. Tickets have been issued but will not be valid until payment is received.<br>
<strong>Payment Instructions</strong><br>
Please follow the instructions below to complete your payment:<br>
{% if settings.offline_payment_instructions %}
{{ settings.offline_payment_instructions }}<br>
{% endif %}

{% else %}
Congratulations! Your order for <strong>{{ event.title }}</strong> on <strong>{{ event.date }}</strong> at <strong>{{ event.time }}</strong> was successful. Please find your order details below.<br>
{% endif %}

<strong>Event Details</strong><br>
<strong>Event Name:</strong> {{ event.title }}<br>
<strong>Date & Time:</strong> {{ event.date }} at {{ event.time }}<br>
{% if event.full_address %}<strong>Location:</strong> {{ event.full_address }}<br>{% endif %}
<br>

{% if settings.post_checkout_message %}
<strong>Additional Information</strong><br>
{{ settings.post_checkout_message }}<br>
{% endif %}

<strong>Order Summary</strong><br>
<strong>Order Number:</strong> {{ order.number }}<br>
<strong>Total Amount:</strong> {{ order.total }}<br>

If you have any questions or need assistance, please contact <a href="mailto:{{ settings.support_email }}">{{ settings.support_email }}</a>.<br>

Best regards,<br>
{{ organizer.name }}
LIQUID
            ],
            EmailTemplateType::ATTENDEE_TICKET->value => [
                'subject' => '🎟️ Your Ticket for {{ event.title }}',
                'body' => <<<'LIQUID'
<strong>You're going to {{ event.title }}! 🎉</strong><br>

{% if order.is_awaiting_offline_payment %}
<strong>ℹ️ Payment Pending:</strong> Your order is pending payment. Tickets have been issued but will not be valid until payment is received.<br>
{% endif %}

Hi {{ attendee.name }},<br>

Please find your ticket details below.<br>

<strong>Event Information</strong><br>
<strong>Event:</strong> {{ event.title }}<br>
<strong>Date:</strong> {{ event.date }}<br>
<strong>Time:</strong> {{ event.time }}<br>
{% if event.full_address %}<strong>Location:</strong> {{ event.full_address }}<br>{% endif %}
<br>

<strong>Your Ticket</strong><br>
<strong>Ticket Type:</strong> {{ ticket.name }}<br>
<strong>Price:</strong> {{ ticket.price }}<br>
<strong>Attendee:</strong> {{ attendee.name }}<br>

<strong>💡Remember:</strong> Please have your ticket ready when you arrive at the event.<br>

If you have any questions or need assistance, please reply to this email or contact the event organizer at <a href="mailto:{{ settings.support_email }}">{{ settings.support_email }}</a>.<br>

LIQUID
            ],
        ];
    }
}
