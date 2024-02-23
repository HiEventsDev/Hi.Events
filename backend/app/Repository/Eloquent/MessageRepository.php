<?php

namespace TicketKitten\Repository\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use TicketKitten\DomainObjects\Generated\MessageDomainObjectAbstract;
use TicketKitten\DomainObjects\MessageDomainObject;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Models\Message;
use TicketKitten\Repository\Interfaces\MessageRepositoryInterface;

class MessageRepository extends BaseRepository implements MessageRepositoryInterface
{
    protected function getModel(): string
    {
        return Message::class;
    }

    public function getDomainObject(): string
    {
        return MessageDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [MessageDomainObjectAbstract::EVENT_ID, '=', $eventId]
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(MessageDomainObjectAbstract::SUBJECT, 'ilike', '%' . $params->query . '%')
                    ->orWhere(MessageDomainObjectAbstract::MESSAGE, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            $params->sort_by ?? MessageDomainObject::getDefaultSort(),
            $params->sort_direction ?? 'desc',
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

}
