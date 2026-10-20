<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSpamCheckDomainObject;
use HiEvents\DomainObjects\Generated\EventSpamCheckDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\EventSpamCheck;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<EventSpamCheckDomainObject>
 */
class EventSpamCheckRepository extends BaseRepository implements EventSpamCheckRepositoryInterface
{
    protected function getModel(): string
    {
        return EventSpamCheck::class;
    }

    public function getDomainObject(): string
    {
        return EventSpamCheckDomainObject::class;
    }

    public function getFlaggedEventsForAdmin(?string $search, int $perPage): LengthAwarePaginator
    {
        $this->model = $this->model
            ->where(EventSpamCheckDomainObjectAbstract::STATUS, EventSpamCheckStatus::FLAGGED->name)
            ->whereHas('event', function ($query) use ($search) {
                $query->where('status', EventStatus::PENDING_MANUAL_REVIEW->name);

                if ($search) {
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery->where('title', 'ilike', '%'.$search.'%')
                            ->orWhereHas('organizer', function ($organizerQuery) use ($search) {
                                $organizerQuery->where('name', 'ilike', '%'.$search.'%');
                            })
                            ->orWhereHas('account', function ($accountQuery) use ($search) {
                                $accountQuery->where('email', 'ilike', '%'.$search.'%');
                            });
                    });
                }
            })
            ->orderBy(EventSpamCheckDomainObjectAbstract::CHECKED_AT, 'desc');

        $this->loadRelation(new Relationship(domainObject: EventDomainObject::class, nested: [
            new Relationship(domainObject: OrganizerDomainObject::class, name: 'organizer'),
            new Relationship(domainObject: AccountDomainObject::class, name: 'account'),
        ], name: 'event'));

        return $this->paginate($perPage);
    }
}
