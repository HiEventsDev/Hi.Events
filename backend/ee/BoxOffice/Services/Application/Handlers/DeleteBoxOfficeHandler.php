<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers;

use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;

class DeleteBoxOfficeHandler
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $boxOfficeId): void
    {
        $boxOffice = $this->boxOfficeRepository->findFirstWhere([
            'event_id' => $eventId,
            'id' => $boxOfficeId,
        ]);

        if ($boxOffice === null) {
            throw new ResourceNotFoundException(__('Box office not found'));
        }

        if ($boxOffice->getIsSystemDefault()) {
            throw new ResourceConflictException(__('The default box office can\'t be deleted.'));
        }

        $this->boxOfficeRepository->deleteWhere([
            'id' => $boxOfficeId,
            'event_id' => $eventId,
        ]);

        $this->featureUsage->forget();
    }
}
