<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\AccountUserDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class UnpublishedEventMailPolicy
{
    private const WITHHELD_STATUSES = [
        EventStatus::DRAFT,
        EventStatus::PENDING_MANUAL_REVIEW,
    ];

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
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

        $verifiedMemberEmails = $this->accountUserRepository
            ->loadRelation(new Relationship(domainObject: UserDomainObject::class, name: 'user'))
            ->findWhere([
                AccountUserDomainObjectAbstract::ACCOUNT_ID => $event->getAccountId(),
                AccountUserDomainObjectAbstract::STATUS => UserStatus::ACTIVE->name,
            ])
            ->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getUser())
            ->filter(fn (?UserDomainObject $user) => $user?->getEmailVerifiedAt() !== null)
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
