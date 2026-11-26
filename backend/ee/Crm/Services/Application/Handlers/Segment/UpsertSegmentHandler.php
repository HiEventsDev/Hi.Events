<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment;

use HiEvents\DomainObjects\CrmSegmentDomainObject;
use HiEvents\DomainObjects\Generated\CrmSegmentDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSegmentRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\UpsertSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;

class UpsertSegmentHandler
{
    public const MAX_SEGMENTS_PER_ORGANIZER = 100;

    public function __construct(
        private readonly CrmSegmentRepositoryInterface $segmentRepository,
        private readonly SegmentDefinitionNormalizer $normalizer,
    ) {}

    /**
     * @throws InvalidSegmentDefinitionException
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws PermissionRequiredException
     */
    public function handle(UpsertSegmentDTO $dto): CrmSegmentDomainObject
    {
        $existing = $dto->segment_id === null ? null : $this->segmentRepository->findFirstWhere([
            CrmSegmentDomainObjectAbstract::ID => $dto->segment_id,
            CrmSegmentDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]);

        if ($dto->segment_id !== null && $existing === null) {
            throw new ResourceNotFoundException(__('Segment not found'));
        }

        $keepsHiddenRules = $existing !== null && ! $dto->can_view_financials && SegmentDefinitionNormalizer::hasFinancialRules($existing->getDefinition());

        if ($keepsHiddenRules && ! $this->normalizer->isStrippedCopyOf($dto->definition, $existing->getDefinition())) {
            throw new PermissionRequiredException(__('This segment filters by spend, so only members who can view financials can change its rules.'));
        }

        $definition = $keepsHiddenRules ? $existing->getDefinition() : $this->normalizer->normalize($dto->definition, $dto->can_view_financials);

        if ($definition['rules'] === []) {
            throw new InvalidSegmentDefinitionException(['definition.rules' => __('Add at least one rule.')]);
        }

        $attributes = [
            CrmSegmentDomainObjectAbstract::NAME => trim($dto->name),
            CrmSegmentDomainObjectAbstract::DESCRIPTION => $dto->description,
            CrmSegmentDomainObjectAbstract::DEFINITION => $definition,
        ];

        if ($existing !== null) {
            $segment = $this->segmentRepository->updateFromArray($existing->getId(), $attributes);
        } else {
            if ($this->segmentRepository->countWhere([CrmSegmentDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id]) >= self::MAX_SEGMENTS_PER_ORGANIZER) {
                throw new ResourceConflictException(__('You have reached the maximum number of segments.'));
            }

            $segment = $this->segmentRepository->create([
                ...$attributes,
                CrmSegmentDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
                CrmSegmentDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            ]);
        }

        return $segment->setUsages($this->segmentRepository->findUsages($dto->organizer_id, [$segment->getId()])->all());
    }
}
