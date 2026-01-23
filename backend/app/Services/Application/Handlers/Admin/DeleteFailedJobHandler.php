<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Models\FailedJob;

class DeleteFailedJobHandler
{
    public function handle(int $id): bool
    {
        return FailedJob::where('id', $id)->delete() > 0;
    }

    public function deleteAll(): int
    {
        return FailedJob::query()->delete();
    }
}
