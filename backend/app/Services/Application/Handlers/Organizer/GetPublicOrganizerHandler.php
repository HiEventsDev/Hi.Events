<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;

class GetPublicOrganizerHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly AccountSubscriptionAccessService $accountSubscriptionAccessService,
    ) {}

    public function handle(int $organizerId): OrganizerDomainObject
    {
        /** @var OrganizerDomainObject $organizer */
        $organizer = $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->loadRelation(OrganizerSettingDomainObject::class)
            ->loadRelation(new Relationship(LocationDomainObject::class, name: 'location_record'))
            ->findById($organizerId);

        return $organizer->setBrandingRemoved(
            $this->accountSubscriptionAccessService->hasActiveSubscription($organizer->getAccountId())
        );
    }
}
