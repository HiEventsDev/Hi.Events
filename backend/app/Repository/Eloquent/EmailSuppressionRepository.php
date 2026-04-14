<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EmailSuppressionDomainObject;
use HiEvents\Models\EmailSuppression;
use HiEvents\Repository\Interfaces\EmailSuppressionRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<EmailSuppressionDomainObject>
 */
class EmailSuppressionRepository extends BaseRepository implements EmailSuppressionRepositoryInterface
{
    protected function getModel(): string
    {
        return EmailSuppression::class;
    }

    public function getDomainObject(): string
    {
        return EmailSuppressionDomainObject::class;
    }

    public function isEmailSuppressed(string $email, ?int $accountId, string $reason): bool
    {
        $query = $this->model->newQuery()
            ->where('email', strtolower($email))
            ->where('reason', $reason)
            ->whereNull('deleted_at');

        if ($accountId !== null) {
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                    ->orWhereNull('account_id');
            });
        }

        return $query->exists();
    }

    public function findByEmail(string $email, ?int $accountId = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('email', strtolower($email))
            ->whereNull('deleted_at');

        if ($accountId !== null) {
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                    ->orWhereNull('account_id');
            });
        }

        return $this->handleResults($query->get());
    }

    public function findOrCreateSuppression(array $uniqueAttributes, array $additionalValues): EmailSuppressionDomainObject
    {
        $model = $this->model->newQuery()
            ->whereNull('deleted_at')
            ->updateOrCreate($uniqueAttributes, $additionalValues);

        $this->resetModel();

        return $this->handleSingleResult($model);
    }
}
