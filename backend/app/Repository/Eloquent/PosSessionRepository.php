<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\PosSessionDomainObject;
use HiEvents\Models\PosSession;
use HiEvents\Repository\Interfaces\PosSessionRepositoryInterface;
use Illuminate\Support\Collection;

class PosSessionRepository extends BaseRepository implements PosSessionRepositoryInterface
{
    protected function getModel(): string
    {
        return PosSession::class;
    }

    public function getDomainObject(): string
    {
        return PosSessionDomainObject::class;
    }

    public function findByEventId(int $eventId): Collection
    {
        return $this->handleResults(
            $this->model->where('event_id', $eventId)
                ->orderBy('created_at', 'desc')
                ->paginate()
        );
    }

    public function findActiveByEventId(int $eventId): Collection
    {
        return $this->handleResults(
            $this->model->where('event_id', $eventId)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }
}
