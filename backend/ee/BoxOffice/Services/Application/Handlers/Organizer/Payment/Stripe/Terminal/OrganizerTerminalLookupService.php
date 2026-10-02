<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class OrganizerTerminalLookupService
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function findOrFail(int $organizerId, int $accountId): OrganizerDomainObject
    {
        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->loadRelation(new Relationship(domainObject: LocationDomainObject::class, name: 'location_record'))
            ->findFirstWhere([
                'id' => $organizerId,
                'account_id' => $accountId,
            ]);

        if ($organizer === null) {
            throw new ResourceNotFoundException(__('Organizer not found.'));
        }

        return $organizer;
    }
}
