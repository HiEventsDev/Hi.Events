<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression;

use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO\ClearBounceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\BounceReactivationPolicy;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

class ClearBounceHandler
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly ConsentRecorder $consentRecorder,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(ClearBounceDTO $dto): void
    {
        $this->databaseManager->transaction(function () use ($dto): void {
            $this->projectionRepository->lockOrganizer($dto->organizer_id);

            $contact = $this->contactRepository->findFirstWhere(['id' => $dto->contact_id, 'organizer_id' => $dto->organizer_id]);
            $email = $contact === null ? null : $this->contactRepository->findEmails($contact->getId())
                ->first(static fn (object $row) => (int) $row->id === $dto->contact_email_id);

            if ($email === null) {
                throw new ResourceNotFoundException(__('Email address not found'));
            }

            if ($email->consent_action !== CrmConsentAction::BOUNCED->value) {
                throw new ResourceConflictException(__('Only a bounced address can be reactivated.'));
            }

            $availableAt = BounceReactivationPolicy::availableAt($email->provider_rejected_at);

            if ($availableAt !== null) {
                throw new ResourceConflictException(__('The email provider refused this address on :date. It can be reactivated from :available.', [
                    'date' => Carbon::parse($email->provider_rejected_at)->isoFormat('LL'),
                    'available' => $availableAt->isoFormat('LL'),
                ]));
            }

            $this->consentRecorder->recordForEmail(
                organizerId: $dto->organizer_id,
                contactId: $dto->contact_id,
                email: $email->email,
                action: CrmConsentAction::BOUNCE_CLEARED,
                source: CrmConsentSource::MANUAL,
                userId: $dto->user_id,
            );
        });
    }
}
