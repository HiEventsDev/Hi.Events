<?php

namespace HiEvents\Repository\Interfaces;

use Exception;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @template T of DomainObjectInterface
 */
interface RepositoryInterface
{
    /** @var array */
    public const DEFAULT_COLUMNS = ['*'];

    /** @var string */
    public const DEFAULT_ORDER_DIRECTION = 'asc';

    /** @var int */
    public const DEFAULT_PAGINATE_LIMIT = 20;

    /** @var int */
    public const MAX_PAGINATE_LIMIT = 100;

    /**
     * Return the FQCL of the domain object associated with this repository
     *
     * @return class-string<T>
     */
    public function getDomainObject(): string;

    /**
     * @return Collection<T>
     */
    public function all(array $columns = self::DEFAULT_COLUMNS): Collection;

    /**
     * @return LengthAwarePaginator<T>
     */
    public function paginate(
        int $limit = self::DEFAULT_PAGINATE_LIMIT,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator;

    /**
     * @return LengthAwarePaginator<T>
     */
    public function paginateWhere(
        array $where,
        int $limit = self::DEFAULT_PAGINATE_LIMIT,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator;

    /**
     * @return LengthAwarePaginator<T>
     */
    public function simplePaginateWhere(
        array $where,
        ?int $limit = null,
        array $columns = self::DEFAULT_COLUMNS,
    ): Paginator;

    /**
     * @return LengthAwarePaginator<T>
     */
    public function paginateEloquentRelation(
        Relation $relation,
        int $limit = self::DEFAULT_PAGINATE_LIMIT,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator;

    /**
     * @return T
     */
    public function findById(int $id, array $columns = self::DEFAULT_COLUMNS): DomainObjectInterface;

    /**
     * @return T|null
     */
    public function findFirst(int $id, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface;

    /**
     * @param  OrderAndDirection[]  $orderAndDirections
     * @return Collection<T>
     */
    public function findWhere(
        array $where,
        array $columns = self::DEFAULT_COLUMNS,
        /** @var OrderAndDirection[] */
        array $orderAndDirections = [],
        ?int $limit = null,
    ): Collection;

    /**
     * @return T|null
     */
    public function findFirstWhere(array $where, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface;

    /**
     * @return T|null
     */
    public function findFirstByField(
        string $field,
        ?string $value = null,
        array $columns = ['*']
    ): ?DomainObjectInterface;

    /**
     * @return Collection<T>
     *
     * @throws Exception
     */
    public function findWhereIn(string $field, array $values, array $additionalWhere = [], array $columns = self::DEFAULT_COLUMNS): Collection;

    /**
     * @return T
     */
    public function create(array $attributes): DomainObjectInterface;

    public function insert(array $inserts): bool;

    /**
     * @return T
     */
    public function updateFromDomainObject(int $id, DomainObjectInterface $domainObject): DomainObjectInterface;

    /**
     * @return T
     */
    public function updateFromArray(int $id, array $attributes): DomainObjectInterface;

    /**
     * @return int Number of affected rows
     */
    public function updateWhere(array $attributes, array $where): int;

    /**
     * @return T
     */
    public function updateByIdWhere(int $id, array $attributes, array $where): DomainObjectInterface;

    public function deleteById(int $id): bool;

    public function deleteWhere(array $conditions): int;

    public function increment(int|float $id, string $column, int|float $amount = 1): int;

    public function decrement(int|float $id, string $column, int|float $amount = 1): int;

    public function incrementWhere(array $where, string $column, int|float $amount = 1): int;

    public function decrementEach(array $where, array $columns, array $extra = []): int;

    public function incrementEach(array $columns, array $additionalUpdates = [], ?array $where = null);

    public function countWhere(array $conditions): int;

    public function includeDeleted(): static;

    public function loadRelation(string|Relationship $relationship): static;
}
