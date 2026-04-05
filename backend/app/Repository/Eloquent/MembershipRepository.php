<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\MembershipDomainObject;
use HiEvents\Models\Membership;
use HiEvents\Repository\Interfaces\MembershipRepositoryInterface;
use Illuminate\Support\Collection;

class MembershipRepository extends BaseRepository implements MembershipRepositoryInterface
{
    protected function getModel(): string
    {
        return Membership::class;
    }

    public function getDomainObject(): string
    {
        return MembershipDomainObject::class;
    }

    public function findByEmail(string $email, int $accountId): Collection
    {
        return $this->handleResults(
            $this->model->where('member_email', $email)
                ->where('account_id', $accountId)
                ->orderBy('created_at', 'desc')
                ->paginate()
        );
    }

    public function findByPlanId(int $planId): Collection
    {
        return $this->handleResults(
            $this->model->where('membership_plan_id', $planId)
                ->orderBy('created_at', 'desc')
                ->paginate()
        );
    }
}
