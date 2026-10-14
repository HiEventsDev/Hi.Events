<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;

class UnpublishedEventMailPolicy
{
    private const WITHHELD_STATUSES = [
        EventStatus::DRAFT,
        EventStatus::PENDING_MANUAL_REVIEW,
    ];

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param  string[]  $recipientEmails
     */
    public function shouldWithholdEventContent(EventDomainObject $event, array $recipientEmails): bool
    {
        $currentEvent = $this->eventRepository->findFirstWhere([
            EventDomainObjectAbstract::ID => $event->getId(),
        ]);

        if (! $this->isWithheldStatus(($currentEvent ?? $event)->getStatus())) {
            return false;
        }

        if ($recipientEmails === []) {
            return false;
        }

        $verifiedMemberEmails = ($this->userRepository->findUsersByAccountId($event->getAccountId()) ?? collect())
            ->filter(fn (UserDomainObject $user) => $user->getEmailVerifiedAt() !== null)
            ->map(fn (UserDomainObject $user) => mb_strtolower(trim($user->getEmail())))
            ->all();

        foreach ($recipientEmails as $recipientEmail) {
            if (! in_array(mb_strtolower(trim($recipientEmail)), $verifiedMemberEmails, true)) {
                return true;
            }
        }

        return false;
    }

    private function isWithheldStatus(?string $status): bool
    {
        foreach (self::WITHHELD_STATUSES as $withheldStatus) {
            if ($withheldStatus->name === $status) {
                return true;
            }
        }

        return false;
    }
}
