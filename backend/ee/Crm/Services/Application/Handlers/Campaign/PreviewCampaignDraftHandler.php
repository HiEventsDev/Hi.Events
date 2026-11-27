<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO\PreviewCampaignDraftDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class PreviewCampaignDraftHandler
{
    public function __construct(
        private readonly CampaignMailFactory $mailFactory,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CampaignOrganizerDeletedException
     */
    public function handle(PreviewCampaignDraftDTO $dto): CampaignMail
    {
        $event = $dto->event_id === null ? null : $this->eventRepository->findFirstWhere([
            EventDomainObjectAbstract::ID => $dto->event_id,
            EventDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]);

        return $this->mailFactory->make(
            (new CrmCampaignDomainObject)
                ->setAccountId($dto->account_id)
                ->setOrganizerId($dto->organizer_id)
                ->setSubject($dto->subject)
                ->setPreheader($dto->preheader)
                ->setBody($dto->body)
                ->setEventId($event?->getId())
                ->setEarlyAccessProductIds($event === null || $dto->early_access_product_ids === [] ? null : $dto->early_access_product_ids),
            null,
            $this->recipient($dto),
        );
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function recipient(PreviewCampaignDraftDTO $dto): CampaignRecipientDTO
    {
        if ($dto->contact_id === null) {
            return $dto->viewer;
        }

        $contact = $this->contactRepository->findFirstWhere(['id' => $dto->contact_id, 'organizer_id' => $dto->organizer_id]);

        if ($contact === null) {
            throw new ResourceNotFoundException(__('Contact not found'));
        }

        return new CampaignRecipientDTO(first_name: $contact->getFirstName(), last_name: $contact->getLastName(), contact_id: $contact->getId());
    }
}
