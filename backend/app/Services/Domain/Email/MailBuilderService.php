<?php

namespace HiEvents\Services\Domain\Email;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use HiEvents\Mail\Occurrence\OccurrenceCancellationMail;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;

class MailBuilderService
{
    public function __construct(
        private readonly EmailTemplateService $emailTemplateService,
        private readonly EmailTokenContextBuilder $tokenContextBuilder,
    ) {
    }

    public function buildAttendeeTicketMail(
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): AttendeeTicketMail {
        $renderedTemplate = $this->renderAttendeeTicketTemplate(
            $attendee,
            $order,
            $event,
            $eventSettings,
            $organizer,
            $occurrence,
        );

        return new AttendeeTicketMail(
            order: $order,
            attendee: $attendee,
            event: $event,
            eventSettings: $eventSettings,
            organizer: $organizer,
            renderedTemplate: $renderedTemplate,
            occurrence: $occurrence,
        );
    }

    public function buildOrderSummaryMail(
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?InvoiceDomainObject $invoice = null,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): OrderSummary {
        $renderedTemplate = $this->renderOrderSummaryTemplate(
            $order,
            $event,
            $eventSettings,
            $organizer,
            $occurrence,
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

    private function renderAttendeeTicketTemplate(
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): ?RenderedEmailTemplateDTO {
        $template = $this->emailTemplateService->getTemplateByType(
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
            $eventSettings,
            $occurrence,
        );

        return $this->emailTemplateService->renderTemplate($template, $context);
    }

    private function renderOrderSummaryTemplate(
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): ?RenderedEmailTemplateDTO {
        $template = $this->emailTemplateService->getTemplateByType(
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
            $eventSettings,
            $occurrence,
        );

        return $this->emailTemplateService->renderTemplate($template, $context);
    }

    public function buildOccurrenceCancellationMail(
        EventDomainObject $event,
        EventOccurrenceDomainObject $occurrence,
        OrganizerDomainObject $organizer,
        EventSettingDomainObject $eventSettings,
        bool $refundOrders = false,
    ): OccurrenceCancellationMail {
        $renderedTemplate = $this->renderOccurrenceCancellationTemplate(
            $event,
            $occurrence,
            $eventSettings,
            $organizer,
            $refundOrders,
        );

        $startDate = DateHelper::convertFromUTC($occurrence->getStartDate(), $event->getTimezone());
        $formattedDate = (new Carbon($startDate))->format('F j, Y g:i A');

        return new OccurrenceCancellationMail(
            event: $event,
            occurrence: $occurrence,
            organizer: $organizer,
            eventSettings: $eventSettings,
            formattedDate: $formattedDate,
            refundOrders: $refundOrders,
            renderedTemplate: $renderedTemplate,
        );
    }

    private function renderOccurrenceCancellationTemplate(
        EventDomainObject $event,
        EventOccurrenceDomainObject $occurrence,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        bool $refundOrders = false,
    ): ?RenderedEmailTemplateDTO {
        $template = $this->emailTemplateService->getTemplateByType(
            type: EmailTemplateType::OCCURRENCE_CANCELLATION,
            accountId: $event->getAccountId(),
            eventId: $event->getId(),
            organizerId: $organizer->getId()
        );

        if (!$template) {
            return null;
        }

        $context = $this->tokenContextBuilder->buildOccurrenceCancellationContext(
            $event,
            $occurrence,
            $organizer,
            $eventSettings,
            $refundOrders,
        );

        return $this->emailTemplateService->renderTemplate($template, $context);
    }
}
