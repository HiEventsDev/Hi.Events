<?php

namespace HiEvents\Http\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class QueryParamsDTO extends BaseDTO
{
    public function __construct(
        public readonly ?int        $page = 1,
        public readonly ?int        $per_page = 25,
        public readonly ?string     $sort_by = null,
        public readonly ?string     $sort_direction = null,
        public readonly ?string     $query = null,
        /** @var Collection<FilterFieldDTO> */
        public readonly ?Collection $filter_fields = null,
        public readonly ?array      $includes = null,
        public readonly ?Collection $query_params = null,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        $filterFields = collect();
        foreach ($data['filter_fields'] ?? [] as $field => $conditions) {
            foreach ($conditions as $operator => $value) {
                $filterFields->push(new FilterFieldDTO(
                    field: $field,
                    operator: $operator,
                    value: $value,
                ));
            }
        }

        return new self(
            page: $data['page'] ?? 1,
            per_page: $data['per_page'] ?? 25,
            sort_by: $data['sort_by'] ?? null,
            sort_direction: $data['sort_direction'] ?? null,
            query: $data['query'] ?? null,
            filter_fields: $filterFields,
            includes: $data['includes'] ?? null,
            query_params: collect($data),
        );
    }
}
