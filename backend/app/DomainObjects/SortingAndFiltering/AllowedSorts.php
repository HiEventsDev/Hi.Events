<?php

namespace HiEvents\DomainObjects\SortingAndFiltering;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class AllowedSorts
{
    private Collection $allowedSorts;

    public function __construct(array $allowedSorts)
    {
        $this->allowedSorts = new Collection();
        foreach ($allowedSorts as $key => $directions) {
            if (!isset($directions['asc']) && !isset($directions['desc'])) {
                throw new InvalidArgumentException(
                    sprintf('AllowedSorts for "%s" must contain at least an asc description or a desc description', $key)
                );
            }

            $ascDescription = $directions['asc'] ?? null;
            $descDescription = $directions['desc'] ?? null;

            $this->allowedSorts->push(new AllowedSort(
                key: $key,
                ascDescription: $ascDescription,
                descDescription: $descDescription,
            ));
        }
    }

    public function toArray(): array
    {
        return $this->allowedSorts->mapWithKeys(function (AllowedSort $sort) {
            $sortOptions = [];

            if (!is_null($sort->ascDescription)) {
                $sortOptions['asc'] = $sort->ascDescription;
            }

            if (!is_null($sort->descDescription)) {
                $sortOptions['desc'] = $sort->descDescription;
            }

            return [$sort->key => $sortOptions];
        })->toArray();
    }
}
