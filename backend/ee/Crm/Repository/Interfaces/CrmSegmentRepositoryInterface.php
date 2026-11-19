<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmSegmentDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentUsageDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmSegmentDomainObject>
 */
interface CrmSegmentRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  int[]  $segmentIds
     * @return Collection<int, SegmentUsageDTO>
     */
    public function findUsages(int $organizerId, array $segmentIds): Collection;
}
