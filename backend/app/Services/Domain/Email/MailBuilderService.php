<?php

namespace HiEvents\Services\Domain\Email;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EmailTemplateDomainObject;
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
use HiEvents\Services\Domain\Lineup\DTO\EmailLineupHighlightsDTO;
use HiEvents\Services\Domain\Lineup\EmailLineupHighlightsService;
use HiEvents\Services\Domain\Order\OfflinePaymentInstructionsRenderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Localizable;

class MailBuilderService
{
    use Localizable;

    public function __construct(
        private readonly EmailTemplateService $emailTemplateService,
        private readonly EmailTokenContextBuilder $tokenContextBuilder,
        private readonly OfflinePaymentInstructionsRenderService $offlinePaymentInstructionsRenderService,
        private readonly EmailLineupHighlightsService $lineupHighlightsService,
    ) {}

    public function buildAttendeeTicketMail(
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): AttendeeTicketMail {
        return $this->makeAttendeeTicketMail(
            $this->getAttendeeTicketTemplate($event, $organizer),
            $attendee,
            $order,
            $event,
            $eventSettings,
            $organizer,
            $occurrence,
        );
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     * @return AttendeeTicketMail[]
     */
    public function buildAttendeeTicketMails(
        Collection $attendees,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
    ): array {
        $attendees = $attendees
            ->sortBy([
                fn (AttendeeDomainObject $a, AttendeeDomainObject $b) => $a->getEventOccurrence()?->getStartDate() <=> $b->getEventOccurrence()?->getStartDate(),
                fn (AttendeeDomainObject $a, AttendeeDomainObject $b) => $a->getId() <=> $b->getId(),
            ])
            ->values();

        $template = $this->getAttendeeTicketTemplate($event, $organizer);

        if ($template !== null || $attendees->count() === 1) {
            return $attendees
                ->map(fn (AttendeeDomainObject $attendee) => $this->makeAttendeeTicketMail(
                    $template,
                    $attendee,
                    $order,
                    $event,
                    $eventSettings,
                    $organizer,
                    $attendee->getEventOccurrence(),
                ))
                ->all();
        }

        return [
            new AttendeeTicketMail(
                order: $order,
                attendee: $attendees->first(),
                event: $event,
                eventSettings: $eventSettings,
                organizer: $organizer,
                occurrence: $attendees->first()->getEventOccurrence(),
                attendees: $attendees,
                lineup: $this->lineupHighlights(
                    $event,
                    $eventSettings,
                    $this->sharedOccurrence($attendees),
                    $attendees->first()->getLocale(),
                ),
            ),
        ];
    }

    public function buildOrderSummaryMail(
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?InvoiceDomainObject $invoice = null,
    ): OrderSummary {
        $renderedTemplate = $this->renderOrderSummaryTemplate(
            $order,
            $event,
            $eventSettings,
            $organizer,
        );

        if (! $renderedTemplate) {
            $this->offlinePaymentInstructionsRenderService->render($order, $event, $organizer, $eventSettings);
        }

        return new OrderSummary(
            order: $order,
            event: $event,
            organizer: $organizer,
            eventSettings: $eventSettings,
            invoice: $invoice,
            renderedTemplate: $renderedTemplate,
            lineup: $renderedTemplate ? null : $this->lineupHighlights($event, $eventSettings, $order->getSingleEventOccurrence(), $order->getLocale()),
        );
    }

    private function lineupHighlights(
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        ?EventOccurrenceDomainObject $occurrence,
        ?string $locale,
    ): ?EmailLineupHighlightsDTO {
        return $this->withLocale($locale, fn () => $this->lineupHighlightsService->build($event, $eventSettings, $occurrence));
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     */
    private function sharedOccurrence(Collection $attendees): ?EventOccurrenceDomainObject
    {
        $occurrenceIds = $attendees->map(fn (AttendeeDomainObject $attendee) => $attendee->getEventOccurrenceId())->unique();

        return $occurrenceIds->count() === 1 ? $attendees->first()->getEventOccurrence() : null;
    }

    private function makeAttendeeTicketMail(
        ?EmailTemplateDomainObject $template,
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?EventOccurrenceDomainObject $occurrence,
    ): AttendeeTicketMail {
        return new AttendeeTicketMail(
            order: $order,
            attendee: $attendee,
            event: $event,
            eventSettings: $eventSettings,
            organizer: $organizer,
            renderedTemplate: $template === null ? null : $this->renderAttendeeTicketTemplate(
                $template,
                $attendee,
                $order,
                $event,
                $eventSettings,
                $organizer,
                $occurrence,
            ),
            occurrence: $occurrence,
            lineup: $template === null ? $this->lineupHighlights($event, $eventSettings, $occurrence, $attendee->getLocale()) : null,
        );
    }

    private function getAttendeeTicketTemplate(
        EventDomainObject $event,
        OrganizerDomainObject $organizer,
    ): ?EmailTemplateDomainObject {
        return $this->emailTemplateService->getTemplateByType(
            type: EmailTemplateType::ATTENDEE_TICKET,
            accountId: $event->getAccountId(),
            eventId: $event->getId(),
            organizerId: $organizer->getId()
        );
    }

    private function renderAttendeeTicketTemplate(
        EmailTemplateDomainObject $template,
        AttendeeDomainObject $attendee,
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): RenderedEmailTemplateDTO {
        $context = $this->tokenContextBuilder->buildAttendeeTicketContext(
            $attendee,
            $order,
            $event,
            $organizer,
            $eventSettings,
            $occurrence,
        );
        $context['lineup'] = $this->lineupHighlightsService->toTemplateContext(
            $this->lineupHighlights($event, $eventSettings, $occurrence, $attendee->getLocale()),
        );

        return $this->emailTemplateService->renderTemplate($template, $context);
    }

    private function renderOrderSummaryTemplate(
        OrderDomainObject $order,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
    ): ?RenderedEmailTemplateDTO {
        $template = $this->emailTemplateService->getTemplateByType(
            type: EmailTemplateType::ORDER_CONFIRMATION,
            accountId: $event->getAccountId(),
            eventId: $event->getId(),
            organizerId: $organizer->getId()
        );

        if (! $template) {
            return null;
        }

        $context = $this->tokenContextBuilder->buildOrderConfirmationContext(
            $order,
            $event,
            $organizer,
            $eventSettings,
        );
        $context['lineup'] = $this->lineupHighlightsService->toTemplateContext(
            $this->lineupHighlights($event, $eventSettings, $order->getSingleEventOccurrence(), $order->getLocale()),
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

        if (! $template) {
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
