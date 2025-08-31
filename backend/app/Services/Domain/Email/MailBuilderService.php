<?php

namespace HiEvents\Services\Domain\Email;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;

class MailBuilderService
{
    public function __construct(
        private readonly EmailTemplateService $emailTemplateService,
        private readonly EmailTokenContextBuilder $tokenContextBuilder,
    ) {
    }

    /**
     * Build attendee ticket email
     */
    public function buildAttendeeTicketMail(
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer
    ): AttendeeTicketMail {
        $renderedTemplate = $this->renderAttendeeTicketTemplate(
            $attendee,
            $order,
            $event,
            $eventSettings,
            $organizer
        );

        return new AttendeeTicketMail(
            order: $order,
            attendee: $attendee,
            event: $event,
            eventSettings: $eventSettings,
            organizer: $organizer,
            renderedTemplate: $renderedTemplate,
        );
    }

    /**
     * Build order summary email
     */
    public function buildOrderSummaryMail(
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?InvoiceDomainObject $invoice = null
    ): OrderSummary {
        $renderedTemplate = $this->renderOrderSummaryTemplate(
            $order,
            $event,
            $eventSettings,
            $organizer
        );

        return new OrderSummary(
            order: $order,
            event: $event,
            organizer: $organizer,
            eventSettings: $eventSettings,
            invoice: $invoice,
            renderedTemplate: $renderedTemplate,
        );
    }

    /**
     * Render attendee ticket template
     */
    private function renderAttendeeTicketTemplate(
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer
    ): ?RenderedEmailTemplateDTO {
        $template = $this->emailTemplateService->getTemplate(
            type: EmailTemplateType::ATTENDEE_TICKET,
            accountId: $event->getAccountId(),
            eventId: $event->getId(),
            organizerId: $organizer->getId()
        );

        if (!$template) {
            return null;
        }

        $context = $this->tokenContextBuilder->buildAttendeeTicketContext(
            $attendee,
            $order,
            $event,
            $organizer,
            $eventSettings
        );

        return $this->emailTemplateService->renderTemplate($template, $context);
    }

    /**
     * Render order summary template
     */
    private function renderOrderSummaryTemplate(
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer
    ): ?RenderedEmailTemplateDTO {
        $template = $this->emailTemplateService->getTemplate(
            type: EmailTemplateType::ORDER_CONFIRMATION,
            accountId: $event->getAccountId(),
            eventId: $event->getId(),
            organizerId: $organizer->getId()
        );

        if (!$template) {
            return null;
        }

        $context = $this->tokenContextBuilder->buildOrderConfirmationContext(
            $order,
            $event,
            $organizer,
            $eventSettings
        );

        return $this->emailTemplateService->renderTemplate($template, $context);
    }
}
