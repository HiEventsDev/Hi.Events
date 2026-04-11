<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Models\EmailSuppression;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllEmailSuppressionsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllEmailSuppressionsHandler
{
    private const ALLOWED_SORT_COLUMNS = ['created_at', 'email', 'reason', 'source'];

    public function handle(GetAllEmailSuppressionsDTO $dto): LengthAwarePaginator
    {
        $query = EmailSuppression::query()
            ->select([
                'email_suppressions.*',
                'accounts.name as account_name',
            ])
            ->leftJoin('accounts', 'accounts.id', '=', 'email_suppressions.account_id')
            ->whereNull('email_suppressions.deleted_at');

        if ($dto->search) {
            $query->where('email_suppressions.email', 'ilike', '%' . $dto->search . '%');
        }

        if ($dto->reason) {
            $query->where('email_suppressions.reason', $dto->reason);
        }

        if ($dto->source) {
            $query->where('email_suppressions.source', $dto->source);
        }

        $sortColumn = in_array($dto->sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $dto->sortBy : 'created_at';
        $sortDirection = in_array(strtolower($dto->sortDirection ?? 'desc'), ['asc', 'desc']) ? $dto->sortDirection : 'desc';

        $query->orderBy('email_suppressions.' . $sortColumn, $sortDirection);

        return $query->paginate($dto->perPage);
    }
}
