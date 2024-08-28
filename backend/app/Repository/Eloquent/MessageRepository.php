<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\MessageDomainObjectAbstract;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Message;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

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
