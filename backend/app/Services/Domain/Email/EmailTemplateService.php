<?php

namespace HiEvents\Services\Domain\Email;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EmailTemplateService
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
        private readonly LiquidTemplateRenderer $liquidRenderer,
        private readonly EmailTokenContextBuilder $tokenBuilder,
        private readonly EmailContextHtmlEscaper $contextHtmlEscaper,
        private readonly HtmlPurifierService $purifier,
    ) {}

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
        $renderedBody = $this->renderBody($template->getBody(), $context);

        $cta = null;

        if ($template->getCta()) {
            $templateCta = $template->getCta();
            if (isset($templateCta['label'], $templateCta['url_token'])) {
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

    public function getDefaultTemplate(EmailTemplateType $type): array
    {
        $defaults = $this->getDefaultTemplates();
        $ctaDefaults = $this->getDefaultCTAs();

        $template = $defaults[$type->value] ?? throw new ResourceNotFoundException('No default template for type '.$type->value);

        $template['cta'] = $ctaDefaults[$type->value] ?? null;

        return $template;
    }

    public function previewTemplate(string $subject, string $body, EmailTemplateType $type, ?array $cta = null): array
    {
        $context = $this->tokenBuilder->buildPreviewContext($type->value);

        $renderedBody = $this->renderBody($body, $context);

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
            'context' => $context,
        ];
    }

    private function renderBody(string $body, array $context): string
    {
        return (string) $this->purifier->purify(
            $this->liquidRenderer->render($body, $this->contextHtmlEscaper->escape($context)),
        );
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

    private function getValueFromDotNotation(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (! isset($value[$k])) {
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
            EmailTemplateType::OCCURRENCE_CANCELLATION->value => [
                'label' => __('View Event'),
                'url_token' => 'event.url',
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

{% if lineup.names.size > 0 or lineup.slots.size > 0 %}
<strong>{{ lineup.heading }}</strong><br>
{% if lineup.names.size > 0 %}{{ lineup.names | join: ", " }}<br>{% endif %}
{% for slot in lineup.slots %}{{ slot.time }} · {{ slot.title }}{% if slot.stage %} · {{ slot.stage }}{% endif %}<br>{% endfor %}
<a href="{{ lineup.url }}">{% if lineup.slots.size > 0 %}See the full schedule{% else %}See the full lineup{% endif %}</a><br>
<br>
{% endif %}

{% if settings.post_checkout_message %}
<strong>Additional Information</strong><br>
{{ settings.post_checkout_message }}<br>
{% endif %}

<strong>Order Summary</strong><br>
<strong>Order Number:</strong> {{ order.number }}<br>
<strong>Total Amount:</strong> {{ order.total }}<br>
{% if order.seats %}<strong>Seats:</strong> {{ order.seats }}<br>{% endif %}

If you have any questions or need assistance, please contact <a href="mailto:{{ settings.support_email }}">{{ settings.support_email }}</a>.<br>

Best regards,<br>
{{ organizer.name }}
LIQUID
            ],
            EmailTemplateType::OCCURRENCE_CANCELLATION->value => [
                'subject' => '{{ event.title }} on {{ occurrence.start_date }} has been cancelled',
                'body' => <<<'LIQUID'
Hello,<br>

We're sorry to let you know that <strong>{{ event.title }}</strong> scheduled for <strong>{{ occurrence.start_date }}</strong> at <strong>{{ occurrence.start_time }}</strong> has been cancelled.<br>

{% if cancellation.refund_issued %}
A refund for your order will be processed automatically. Please allow a few business days for the refund to appear on your statement.<br>
{% else %}
If you have any questions about your order, please respond to this email or contact <a href="mailto:{{ settings.support_email }}">{{ settings.support_email }}</a>.<br>
{% endif %}

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

{% if lineup.names.size > 0 or lineup.slots.size > 0 %}
<strong>{{ lineup.heading }}</strong><br>
{% if lineup.names.size > 0 %}{{ lineup.names | join: ", " }}<br>{% endif %}
{% for slot in lineup.slots %}{{ slot.time }} · {{ slot.title }}{% if slot.stage %} · {{ slot.stage }}{% endif %}<br>{% endfor %}
<a href="{{ lineup.url }}">{% if lineup.slots.size > 0 %}See the full schedule{% else %}See the full lineup{% endif %}</a><br>
<br>
{% endif %}

<strong>Your Ticket</strong><br>
<strong>Ticket Type:</strong> {{ ticket.name }}<br>
<strong>Price:</strong> {{ ticket.price }}<br>
{% if attendee.seat %}<strong>Seat:</strong> {{ attendee.seat }}<br>{% endif %}
<strong>Attendee:</strong> {{ attendee.name }}<br>

<strong>💡Remember:</strong> Please have your ticket ready when you arrive at the event.<br>

If you have any questions or need assistance, please reply to this email or contact the event organizer at <a href="mailto:{{ settings.support_email }}">{{ settings.support_email }}</a>.<br>

LIQUID
            ],
        ];
    }
}
