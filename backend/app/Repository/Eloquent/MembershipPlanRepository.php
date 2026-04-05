<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\MembershipPlanDomainObject;
use HiEvents\Models\MembershipPlan;
use HiEvents\Repository\Interfaces\MembershipPlanRepositoryInterface;
use Illuminate\Support\Collection;

class MembershipPlanRepository extends BaseRepository implements MembershipPlanRepositoryInterface
{
    protected function getModel(): string
    {
        return MembershipPlan::class;
    }

    public function getDomainObject(): string
    {
        return MembershipPlanDomainObject::class;
    }

    public function findByOrganizerId(int $organizerId): Collection
    {
        return $this->handleResults(
            $this->model->where('organizer_id', $organizerId)
                ->orderBy('sort_order')
                ->orderBy('created_at', 'desc')
                ->paginate()
        );
    }
}
