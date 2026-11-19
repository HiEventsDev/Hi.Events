<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSuppressionRepositoryInterface;
use Illuminate\Support\Collection;

class ContactEnrichmentService
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmSuppressionRepositoryInterface $suppressionRepository,
    ) {}

    /**
     * @param  Collection<int, CrmContactDomainObject>  $contacts
     */
    public function attachTagsAndSpend(Collection $contacts): void
    {
        if ($contacts->isEmpty()) {
            return;
        }

        $contactIds = $contacts->map(static fn (CrmContactDomainObject $contact) => $contact->getId())->all();
        $tags = $this->contactRepository->findTagsForContacts($contactIds);
        $spend = $this->contactRepository->findSpendForContacts($contactIds);

        $contacts->each(static fn (CrmContactDomainObject $contact) => $contact
            ->setTags($tags->get($contact->getId(), collect()))
            ->setSpend($spend->get($contact->getId(), collect())));
    }

    /**
     * @param  Collection<int, CrmContactDomainObject>  $contacts
     */
    public function attachSuppressions(int $organizerId, Collection $contacts): void
    {
        $details = $this->suppressionRepository->findDetailsForContacts(
            $organizerId,
            $contacts->map(static fn (CrmContactDomainObject $contact) => $contact->getId())->all(),
        );

        $contacts->each(static fn (CrmContactDomainObject $contact) => $contact->setSuppression($details->get($contact->getId())));
    }

    public function attachProfile(CrmContactDomainObject $contact): void
    {
        $this->attachTagsAndSpend(collect([$contact]));
        $this->attachSuppressions($contact->getOrganizerId(), collect([$contact]));

        $contact
            ->setEmails($this->contactRepository->findEmails($contact->getId()))
            ->setNames($this->contactRepository->findObservedNames($contact->getId()))
            ->setNoShowCount($this->contactRepository->countNoShows($contact->getId(), now()));
    }
}
