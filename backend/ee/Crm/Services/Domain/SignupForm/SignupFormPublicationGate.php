<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\SignupForm;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;

class SignupFormPublicationGate
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    public function isPublic(OrganizerDomainObject $organizer): bool
    {
        return $this->isLive($organizer) && $this->isAccountVerified($organizer);
    }

    /**
     * @throws ResourceConflictException
     */
    public function assertCanGoLive(OrganizerDomainObject $organizer): void
    {
        if (! $this->isAccountVerified($organizer)) {
            throw new ResourceConflictException(
                __('Confirm your account\'s email address before making a signup form live. Check your inbox for the confirmation email.'),
            );
        }

        if (! $this->isLive($organizer)) {
            throw new ResourceConflictException(__('Publish your organizer page before making a signup form live.'));
        }
    }

    private function isLive(OrganizerDomainObject $organizer): bool
    {
        return $organizer->getStatus() === OrganizerStatus::LIVE->name;
    }

    private function isAccountVerified(OrganizerDomainObject $organizer): bool
    {
        return $this->accountRepository->findById($organizer->getAccountId())->getAccountVerifiedAt() !== null;
    }
}
