<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\DomainObjects\Generated\ContactDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Contact;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<ContactDomainObject>
 */
class ContactRepository extends BaseRepository implements ContactRepositoryInterface
{
    protected function getModel(): string
    {
        return Contact::class;
    }

    public function getDomainObject(): string
    {
        return ContactDomainObject::class;
    }

    public function findByAccountId(int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            ['contacts.'.ContactDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->orWhere('contacts.'.ContactDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%')
                    ->orWhereRaw(
                        "contacts.first_name || ' ' || contacts.last_name ilike ?",
                        ['%'.$params->query.'%']
                    )
                    ->orWhere('contacts.'.ContactDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere('contacts.'.ContactDomainObjectAbstract::FIRST_NAME, 'ilike', '%'.$params->query.'%');
            };
        }

        $eventIdFilter = $this->extractEventIdFilter($params);
        $this->model = $this->model->select('contacts.*');
        if ($eventIdFilter !== null) {
            $this->model = $this->model
                ->join('attendees', 'attendees.contact_id', '=', 'contacts.id')
                ->where('attendees.event_id', '=', $eventIdFilter)
                ->whereNull('attendees.deleted_at')
                ->distinct();
        }

        $this->model = $this->model->orderBy(
            column: 'contacts.'.$this->validateSortColumn($params->sort_by, ContactDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, ContactDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    private function extractEventIdFilter(QueryParamsDTO $params): ?int
    {
        if (! $params->filter_fields || $params->filter_fields->isEmpty()) {
            return null;
        }
        $match = $params->filter_fields->first(fn ($field) => $field->field === 'event_id');
        if (! $match) {
            return null;
        }
        $value = is_string($match->value) ? $match->value : (string) $match->value;

        return ctype_digit($value) ? (int) $value : null;
    }

    public function findByEmailAndAccountId(string $email, int $accountId): ?ContactDomainObject
    {
        return $this->findFirstWhere([
            [ContactDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
            fn (Builder $builder) => $builder->whereRaw('lower(email) = ?', [strtolower($email)]),
        ]);
    }
}
