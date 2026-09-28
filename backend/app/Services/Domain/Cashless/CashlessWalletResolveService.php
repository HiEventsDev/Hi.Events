<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CashlessWalletDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class CashlessWalletResolveService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly CashlessWalletRepositoryInterface $walletRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CashlessSettingsService $settingsService,
    ) {}

    /**
     * @throws CashlessWalletUnavailableException
     */
    public function resolveByAttendeePublicId(int $eventId, string $attendeePublicId): CashlessWalletDomainObject
    {
        return $this->resolveForAttendee(
            $this->findAttendee($eventId, AttendeeDomainObjectAbstract::PUBLIC_ID, strtoupper(trim($attendeePublicId)))
        );
    }

    /**
     * @throws CashlessWalletUnavailableException
     */
    public function resolveByTicketReference(int $eventId, string $ticketReference): CashlessWalletDomainObject
    {
        $reference = trim($ticketReference);

        return str_starts_with(strtoupper($reference), IdHelper::ATTENDEE_PREFIX_PUBLIC)
            ? $this->resolveByAttendeePublicId($eventId, $reference)
            : $this->resolveForAttendee($this->findAttendee($eventId, AttendeeDomainObjectAbstract::SHORT_ID, $reference));
    }

    /**
     * @throws CashlessWalletUnavailableException
     */
    private function findAttendee(int $eventId, string $field, string $value): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            $field => $value,
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($attendee === null) {
            throw new CashlessWalletUnavailableException(
                __('No ticket was found for this code.')
            );
        }

        if ($attendee->getStatus() !== AttendeeStatus::ACTIVE->name) {
            throw new CashlessWalletUnavailableException(
                __('This ticket is not active, so it cannot hold a cashless balance.')
            );
        }

        return $attendee;
    }

    private function resolveForAttendee(AttendeeDomainObject $attendee): CashlessWalletDomainObject
    {
        $wallet = $this->walletRepository->findFirstWhere([
            CashlessWalletDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
        ]);

        if ($wallet !== null) {
            return $wallet->setAttendee($attendee);
        }

        /** @var EventDomainObject $event */
        $event = $this->eventRepository->findById($attendee->getEventId());

        return $this->walletRepository->create([
            CashlessWalletDomainObjectAbstract::EVENT_ID => $attendee->getEventId(),
            CashlessWalletDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
            CashlessWalletDomainObjectAbstract::CURRENCY => $event->getCurrency(),
            CashlessWalletDomainObjectAbstract::STATUS => $this->settingsService->getSettings($attendee->getEventId())->getCashlessClosedAt() === null
                ? CashlessWalletStatus::ACTIVE->value
                : CashlessWalletStatus::CLOSED->value,
        ])->setAttendee($attendee);
    }
}
