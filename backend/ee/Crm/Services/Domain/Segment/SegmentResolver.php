<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Segment;

use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\DomainObjects\Generated\CrmSegmentDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSegmentRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Exceptions\ResourceNotFoundException;

class SegmentResolver
{
    public function __construct(
        private readonly CrmSegmentRepositoryInterface $segmentRepository,
        private readonly SegmentDefinitionNormalizer $normalizer,
        private readonly SegmentQueryCompiler $compiler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function resolveDefinition(int $organizerId, SegmentReferenceDTO $reference, bool $canViewFinancials): ?array
    {
        if ($reference->segment_id !== null) {
            $segment = $this->segmentRepository->findFirstWhere([
                CrmSegmentDomainObjectAbstract::ID => $reference->segment_id,
                CrmSegmentDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            ]);

            if ($segment === null) {
                throw new ResourceNotFoundException(__('Segment not found'));
            }

            return $this->normalizer->normalize($segment->getDefinition(), $canViewFinancials);
        }

        if ($reference->preset !== null) {
            $preset = CrmSegmentPreset::tryFrom($reference->preset);

            if ($preset === null) {
                throw new ResourceNotFoundException(__('Segment not found'));
            }

            return $preset->definition();
        }

        if ($reference->definition !== null) {
            return $this->normalizer->normalize($reference->definition, $canViewFinancials);
        }

        return null;
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function resolve(int $organizerId, SegmentReferenceDTO $reference, bool $canViewFinancials): ?CompiledSegmentDTO
    {
        $definition = $this->resolveDefinition($organizerId, $reference, $canViewFinancials);

        return $definition === null ? null : $this->compiler->compile($definition, $organizerId, now());
    }
}
