<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventSubscriberDomainObject;
use HiEvents\Models\EventSubscriber;
use HiEvents\Repository\Interfaces\EventSubscriberRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<EventSubscriberDomainObject>
 */
class EventSubscriberRepository extends BaseRepository implements EventSubscriberRepositoryInterface
{
    protected function getModel(): string
    {
        return EventSubscriber::class;
    }

    public function getDomainObject(): string
    {
        return EventSubscriberDomainObject::class;
    }

    public function findByOrganizerId(int $organizerId, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->where('organizer_id', $organizerId)
            ->whereNull('unsubscribed_at')
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function findByToken(string $token): ?EventSubscriberDomainObject
    {
        $model = $this->model->where('token', $token)->first();

        if (!$model) {
            return null;
        }

        return $this->handleSingle($model);
    }

    public function subscriberExists(int $organizerId, string $email): bool
    {
        return $this->model
            ->where('organizer_id', $organizerId)
            ->where('email', $email)
            ->whereNull('unsubscribed_at')
            ->exists();
    }
}
