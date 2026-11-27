<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\SignupForm;

use HiEvents\DomainObjects\CrmSignupFormDomainObject;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\CrmSignupFormDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\CrmSignupFormStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;

class SignupFormPublicLookup
{
    public function __construct(
        private readonly CrmSignupFormRepositoryInterface $signupFormRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly FeatureFlagService $featureFlagService,
        private readonly SignupFormPublicationGate $publicationGate,
    ) {}

    public function findActive(string $publicId): ?CrmSignupFormDomainObject
    {
        return $this->visible($this->signupFormRepository->findFirstWhere([
            CrmSignupFormDomainObjectAbstract::PUBLIC_ID => $publicId,
            CrmSignupFormDomainObjectAbstract::STATUS => CrmSignupFormStatus::ACTIVE->value,
        ]));
    }

    public function findForOrganizerPage(int $organizerId): ?CrmSignupFormDomainObject
    {
        return $this->visible($this->signupFormRepository->findFirstWhere([
            CrmSignupFormDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            CrmSignupFormDomainObjectAbstract::STATUS => CrmSignupFormStatus::ACTIVE->value,
            CrmSignupFormDomainObjectAbstract::SHOW_ON_ORGANIZER_PAGE => true,
        ]));
    }

    private function organizerFor(CrmSignupFormDomainObject $form): ?OrganizerDomainObject
    {
        return $this->organizerRepository->findFirstWhere(['id' => $form->getOrganizerId()]);
    }

    private function visible(?CrmSignupFormDomainObject $form): ?CrmSignupFormDomainObject
    {
        $organizer = $form === null ? null : $this->organizerFor($form);

        if ($organizer === null
            || ! $this->featureFlagService->isEnabled(FeatureFlag::CRM, $form->getAccountId())
            || ! $this->publicationGate->isPublic($organizer)) {
            return null;
        }

        return $form->setOrganizer($organizer);
    }
}
