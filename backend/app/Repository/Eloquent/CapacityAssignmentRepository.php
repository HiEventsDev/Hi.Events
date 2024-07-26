<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\Models\CapacityAssignment;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CapacityAssignmentRepository extends BaseRepository implements CapacityAssignmentRepositoryInterface
{
    protected function getModel(): string
    {
        return CapacityAssignment::class;
    }

    public function getDomainObject(): string
    {
        return CapacityAssignmentDomainObject::class;
    }

    public function findCapacityAssignments(int $eventId): LengthAwarePaginator
    {
        $this->model->orderBy('created_at', 'desc');

        return $this->paginateWhere(
            where: [
                [
                    'event_id', '=', $eventId,
                ],
            ],
            limit: 100,
            page: 1,
        );
    }
}
