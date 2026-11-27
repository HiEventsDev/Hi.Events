<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\UnknownTagException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;

class SetContactTagsHandler
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmTagRepositoryInterface $tagRepository,
        private readonly ContactLookupService $contactLookupService,
        private readonly ContactEnrichmentService $enrichmentService,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    /**
     * @param  int[]  $tagIds
     *
     * @throws ResourceNotFoundException
     * @throws UnknownTagException
     */
    public function handle(int $organizerId, int $contactId, array $tagIds): CrmContactDomainObject
    {
        $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);
        $tagIds = array_values(array_unique($tagIds));

        if ($this->tagRepository->countForOrganizer($organizerId, $tagIds) !== count($tagIds)) {
            throw new UnknownTagException(__('Tag not found'));
        }

        $this->contactRepository->syncTags($organizerId, $contact->getId(), $tagIds);
        $this->cacheVersion->bumpAfterCommit($organizerId);
        $this->enrichmentService->attachProfile($contact);

        return $contact;
    }
}
