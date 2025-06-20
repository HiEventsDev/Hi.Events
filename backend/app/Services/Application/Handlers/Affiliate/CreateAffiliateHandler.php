<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Affiliate;

use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Services\Application\Handlers\Affiliate\DTO\UpsertAffiliateDTO;

class CreateAffiliateHandler
{
    public function __construct(
        private readonly AffiliateRepositoryInterface $affiliateRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $accountId, UpsertAffiliateDTO $dto): AffiliateDomainObject
    {
        $code = strtoupper($dto->code);

        $existingAffiliate = $this->affiliateRepository->findFirstWhere([
            'event_id' => $eventId,
            'code' => $code,
        ]);

        if ($existingAffiliate) {
            throw new ResourceConflictException(__('An affiliate with this code already exists for this event'));
        }

        return $this->affiliateRepository->create([
            'event_id' => $eventId,
            'account_id' => $accountId,
            'name' => $dto->name,
            'code' => $code,
            'email' => $dto->email,
            'status' => $dto->status->value,
        ]);
    }
}
