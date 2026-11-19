<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\SignupForm;

use HiEvents\DomainObjects\CrmPendingSubscriptionDomainObject;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\CrmPendingSubscriptionDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmSignupFormDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\CrmPendingSubscriptionStatus;
use HiEvents\DomainObjects\Status\CrmSignupFormStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmPendingSubscriptionRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO\SubscriptionConfirmationDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\EmailMask;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Support\Carbon;

class SubscriptionConfirmationReader
{
    public function __construct(
        private readonly CrmPendingSubscriptionRepositoryInterface $pendingRepository,
        private readonly CrmSignupFormRepositoryInterface $signupFormRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly SubscriptionTokenService $tokenService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly SignupFormPublicationGate $publicationGate,
    ) {}

    public function find(string $token): ?CrmPendingSubscriptionDomainObject
    {
        return $this->pendingRepository->findFirstWhere([
            CrmPendingSubscriptionDomainObjectAbstract::TOKEN_HASH => $this->tokenService->hash($token),
        ]);
    }

    public function describe(?CrmPendingSubscriptionDomainObject $pending): SubscriptionConfirmationDTO
    {
        $organizer = $pending === null ? null : $this->organizerRepository->findFirstWhere(['id' => $pending->getOrganizerId()]);

        if ($pending === null || $organizer === null || ! $this->featureFlagService->isEnabled(FeatureFlag::CRM, $organizer->getAccountId())) {
            return new SubscriptionConfirmationDTO(SubscriptionConfirmationDTO::INVALID);
        }

        $form = $pending->getFormId() === null ? null : $this->signupFormRepository->findFirstWhere([
            CrmSignupFormDomainObjectAbstract::ID => $pending->getFormId(),
        ]);

        return new SubscriptionConfirmationDTO(
            status: $this->status($pending),
            organizer_id: $organizer->getId(),
            organizer_name: $organizer->getName(),
            masked_email: EmailMask::mask($pending->getEmail()),
            success_message: $form?->getSuccessMessage(),
            form_public_id: $form?->getStatus() === CrmSignupFormStatus::ACTIVE->value && $this->publicationGate->isPublic($organizer)
                ? $form->getPublicId()
                : null,
        );
    }

    public function isConfirmable(CrmPendingSubscriptionDomainObject $pending, OrganizerDomainObject $organizer): bool
    {
        return $this->status($pending) === SubscriptionConfirmationDTO::PENDING
            && $this->featureFlagService->isEnabled(FeatureFlag::CRM, $organizer->getAccountId());
    }

    private function status(CrmPendingSubscriptionDomainObject $pending): string
    {
        return match (true) {
            $pending->getStatus() === CrmPendingSubscriptionStatus::CONFIRMED->value,
            $this->wasRetiredByAnotherConfirmation($pending) => SubscriptionConfirmationDTO::CONFIRMED,
            Carbon::parse($pending->getExpiresAt())->isPast() => SubscriptionConfirmationDTO::EXPIRED,
            default => SubscriptionConfirmationDTO::PENDING,
        };
    }

    private function wasRetiredByAnotherConfirmation(CrmPendingSubscriptionDomainObject $pending): bool
    {
        return $pending->getStatus() === CrmPendingSubscriptionStatus::SUPERSEDED->value
            && $pending->getCreatedAt() !== null
            && $this->pendingRepository->hasConfirmationSince($pending->getOrganizerId(), $pending->getEmail(), Carbon::parse($pending->getCreatedAt()));
    }
}
