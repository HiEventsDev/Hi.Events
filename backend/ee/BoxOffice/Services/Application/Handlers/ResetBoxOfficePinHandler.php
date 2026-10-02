<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers;

use HiEvents\DomainObjects\Generated\BoxOfficeDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeWithPinDTO;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Exceptions\ResourceNotFoundException;

class ResetBoxOfficePinHandler
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly BoxOfficePinService $pinService,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $boxOfficeId): BoxOfficeWithPinDTO
    {
        $where = [
            BoxOfficeDomainObjectAbstract::ID => $boxOfficeId,
            BoxOfficeDomainObjectAbstract::EVENT_ID => $eventId,
        ];

        if ($this->boxOfficeRepository->findFirstWhere($where) === null) {
            throw new ResourceNotFoundException(__('Box office not found'));
        }

        $pin = $this->pinService->generate();

        $this->boxOfficeRepository->updateWhere(
            attributes: [BoxOfficeDomainObjectAbstract::PIN_HASH => $this->pinService->hash($pin)],
            where: $where,
        );

        $this->featureUsage->forget();

        return new BoxOfficeWithPinDTO(
            box_office: $this->boxOfficeRepository->findFirstWhere($where),
            pin: $pin,
        );
    }
}
