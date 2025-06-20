<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Affiliate;

use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteAffiliateHandler
{
    public function __construct(
        private readonly AffiliateRepositoryInterface $affiliateRepository,
    ) {
    }

    public function handle(int $affiliateId, int $eventId): void
    {
        $affiliate = $this->affiliateRepository->findFirstWhere([
            'id' => $affiliateId,
            'event_id' => $eventId
        ]);

        if (!$affiliate) {
            throw new NotFoundHttpException(__('Affiliate not found'));
        }

        $this->affiliateRepository->deleteById($affiliateId);
    }
}