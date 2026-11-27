<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSuppressionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO\SuppressionLookupResultDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;

class LookupSuppressionsHandler
{
    public function __construct(
        private readonly CrmSuppressionRepositoryInterface $suppressionRepository,
        private readonly ContactEmailNormalizer $emailNormalizer,
        private readonly DeletedContactHasher $deletedContactHasher,
    ) {}

    /**
     * @param  string[]  $addresses
     * @return SuppressionLookupResultDTO[]
     */
    public function handle(int $organizerId, array $addresses): array
    {
        $normalized = [];
        foreach ($addresses as $address) {
            $normalized[trim($address)] = $this->emailNormalizer->normalize($address);
        }

        $emails = array_values(array_unique(array_filter($normalized)));
        $contacts = $this->suppressionRepository->findContactsByEmail($organizerId, $emails);
        $details = $this->suppressionRepository->findDetailsForContacts(
            $organizerId,
            $contacts->map(static fn (CrmContactDomainObject $contact) => $contact->getId())->unique()->values()->all(),
        );
        $deleted = $this->suppressionRepository->findTombstoneSuppressions(
            $organizerId,
            array_values(array_diff($emails, $contacts->keys()->all())),
            $this->deletedContactHasher->salt($organizerId),
        );

        $results = [];
        foreach ($normalized as $address => $email) {
            if ($address === '' || isset($results[$email ?? $address])) {
                continue;
            }

            $results[$email ?? $address] = match (true) {
                $email === null => new SuppressionLookupResultDTO(email: $address, result: SuppressionLookupResultDTO::INVALID),
                $contacts->has($email) => new SuppressionLookupResultDTO(
                    email: $email,
                    result: SuppressionLookupResultDTO::CONTACT,
                    contact: $contacts->get($email),
                    suppression: $details->get($contacts->get($email)->getId()),
                ),
                array_key_exists($email, $deleted) => new SuppressionLookupResultDTO(
                    email: $email,
                    result: SuppressionLookupResultDTO::DELETED,
                    deleted_suppression: $deleted[$email],
                ),
                default => new SuppressionLookupResultDTO(email: $email, result: SuppressionLookupResultDTO::NOT_FOUND),
            };
        }

        return array_values($results);
    }
}
