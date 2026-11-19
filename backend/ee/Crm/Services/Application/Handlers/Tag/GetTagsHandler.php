<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use Illuminate\Support\Collection;

class GetTagsHandler
{
    public function __construct(
        private readonly CrmTagRepositoryInterface $tagRepository,
    ) {}

    public function handle(int $organizerId): Collection
    {
        return $this->tagRepository->findByOrganizerIdWithCounts($organizerId);
    }
}
