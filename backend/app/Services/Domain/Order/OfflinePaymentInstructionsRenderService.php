<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Services\Domain\Email\EmailTokenContextBuilder;
use Throwable;

class OfflinePaymentInstructionsRenderService
{
    public function __construct(
        private readonly EmailTokenContextBuilder $tokenContextBuilder,
    )
    {
    }

    public function renderForOrder(OrderDomainObject $order): void
    {
        $event = $order->getEvent();
        $eventSettings = $event?->getEventSettings();
        $organizer = $event?->getOrganizer();

        if (!$event || !$eventSettings || !$organizer) {
            return;
        }

        $this->render($order, $event, $organizer, $eventSettings);
    }

    public function render(
        OrderDomainObject        $order,
        EventDomainObject        $event,
        OrganizerDomainObject    $organizer,
        EventSettingDomainObject $eventSettings,
    ): void
    {
        if (!$eventSettings->getOfflinePaymentInstructions()) {
            return;
        }

        try {
            $context = $this->tokenContextBuilder->buildOrderConfirmationContext(
                order: $order,
                event: $event,
                organizer: $organizer,
                eventSettings: $eventSettings,
            );

            $eventSettings->setOfflinePaymentInstructions(
                $context['settings']['offline_payment_instructions']
            );
        } catch (Throwable) {
            return;
        }
    }
}
