<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Models\FailedJob;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllFailedJobsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllFailedJobsHandler
{
    private const ALLOWED_SORT_COLUMNS = ['failed_at', 'queue', 'connection'];

    public function handle(GetAllFailedJobsDTO $dto): LengthAwarePaginator
    {
        $query = FailedJob::query();

        if ($dto->search) {
            $searchTerm = '%' . $dto->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('payload', 'ilike', $searchTerm)
                    ->orWhere('exception', 'ilike', $searchTerm);
            });
        }

        if ($dto->queue) {
            $query->where('queue', $dto->queue);
        }

        $sortColumn = in_array($dto->sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $dto->sortBy : 'failed_at';
        $sortDirection = in_array(strtolower($dto->sortDirection ?? 'desc'), ['asc', 'desc']) ? $dto->sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($dto->perPage);
    }
}
