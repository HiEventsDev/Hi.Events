<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment;

use HiEvents\DomainObjects\CrmSegmentDomainObject;
use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\CrmSegmentDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSegmentRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\PresetSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\SegmentListDTO;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;

class GetSegmentsHandler
{
    public function __construct(
        private readonly CrmSegmentRepositoryInterface $segmentRepository,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function handle(int $accountId, int $organizerId): SegmentListDTO
    {
        $presets = array_map(
            static fn (CrmSegmentPreset $preset) => new PresetSegmentDTO(key: $preset->value, definition: $preset->definition()),
            $this->availablePresets($accountId),
        );

        $segments = $this->segmentRepository->findWhere(
            where: [CrmSegmentDomainObjectAbstract::ORGANIZER_ID => $organizerId],
            orderAndDirections: [new OrderAndDirection(CrmSegmentDomainObjectAbstract::NAME, OrderAndDirection::DIRECTION_ASC)],
        );

        $usages = $this->segmentRepository
            ->findUsages($organizerId, $segments->map(static fn (CrmSegmentDomainObject $segment) => $segment->getId())->all())
            ->groupBy('segment_id');

        $segments->each(static fn (CrmSegmentDomainObject $segment) => $segment->setUsages(
            $usages->get($segment->getId(), collect())->values()->all(),
        ));

        return new SegmentListDTO(presets: $presets, segments: $segments);
    }

    /**
     * @return CrmSegmentPreset[]
     */
    private function availablePresets(int $accountId): array
    {
        $offersTextMessages = $this->featureFlagService->isEnabled(FeatureFlag::CRM_SMS, $accountId);

        return array_values(array_filter(
            CrmSegmentPreset::cases(),
            static fn (CrmSegmentPreset $preset) => $offersTextMessages || $preset !== CrmSegmentPreset::SMS_SUBSCRIBERS,
        ));
    }
}
