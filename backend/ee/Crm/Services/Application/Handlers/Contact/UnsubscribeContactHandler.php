<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;

class UnsubscribeContactHandler
{
    public function __construct(
        private readonly ContactLookupService $contactLookupService,
        private readonly ConsentRecorder $consentRecorder,
        private readonly ContactEnrichmentService $enrichmentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId, int $userId, CrmMarketingChannel $channel = CrmMarketingChannel::EMAIL): CrmContactDomainObject
    {
        $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);

        if ($channel === CrmMarketingChannel::SMS) {
            $this->consentRecorder->recordSmsOptOut($contact, $userId);
        } else {
            $this->consentRecorder->record(
                contact: $contact,
                action: CrmConsentAction::OPT_OUT,
                source: CrmConsentSource::MANUAL,
                userId: $userId,
            );
        }

        $updated = $this->contactLookupService->findForOrganizer($organizerId, $contact->getId());
        $this->enrichmentService->attachProfile($updated);

        return $updated;
    }
}
