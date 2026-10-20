<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\Models\AccountDeletionRequest;
use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<AccountDeletionRequestDomainObject>
 */
class AccountDeletionRequestRepository extends BaseRepository implements AccountDeletionRequestRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountDeletionRequest::class;
    }

    public function getDomainObject(): string
    {
        return AccountDeletionRequestDomainObject::class;
    }

    public function findDueForReminder(int $daysBefore): Collection
    {
        return $this->runQuery(function () use ($daysBefore) {
            $requests = $this->model
                ->where('status', AccountDeletionRequestStatus::REQUESTED->name)
                ->where('scheduled_deletion_at', '<=', now()->addDays($daysBefore))
                ->whereNull('reminder_sent_at')
                ->get();

            return $this->handleResults($requests);
        });
    }

    public function getAllRequestsWithAccounts(?string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->runQuery(function () use ($search, $status, $perPage) {
            $query = $this->model
                ->with(['account', 'requestedByUser', 'cancelledByUser']);

            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->whereHas('account', function ($accountQuery) use ($search) {
                    $accountQuery->withTrashed()
                        ->where(function ($q) use ($search) {
                            $q->where('accounts.name', 'ilike', "{$search}%")
                                ->orWhere('accounts.email', 'ilike', "{$search}%");
                        });
                });
            }

            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        });
    }
}
