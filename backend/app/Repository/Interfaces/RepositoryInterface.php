<?php

namespace HiEvents\Repository\Interfaces;

use Exception;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @template T
 */
interface RepositoryInterface
{
    /** @var array */
    public const DEFAULT_COLUMNS = ['*'];

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
     * @param array $columns
     * @return Collection<T>
     */
    public function all(array $columns = self::DEFAULT_COLUMNS): Collection;

    /**
     * @param int $limit
     * @param array $columns
     * @return LengthAwarePaginator<T>
     */
    public function paginate(
        int   $limit = self::DEFAULT_PAGINATE_LIMIT,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator;

    /**
     * @param array $where
     * @param int $limit
     * @param array $columns
     * @return LengthAwarePaginator<T>
     */
    public function paginateWhere(
        array $where,
        int   $limit = self::DEFAULT_PAGINATE_LIMIT,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator;

    /**
     * @param array $where
     * @param int|null $limit
     * @param array $columns
     * @return LengthAwarePaginator<T>
     */
    public function simplePaginateWhere(
        array $where,
        int   $limit = null,
        array $columns = self::DEFAULT_COLUMNS,
    ): Paginator;

    /**
     * @param Relation $relation
     * @param int $limit
     * @param array $columns
     * @return LengthAwarePaginator<T>
     */
    public function paginateEloquentRelation(
        Relation $relation,
        int      $limit = self::DEFAULT_PAGINATE_LIMIT,
        array    $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator;

    /**
     * @param int $id
     * @param array $columns
     * @return T
     *
     */
    public function findById(int $id, array $columns = self::DEFAULT_COLUMNS): DomainObjectInterface;

    /**
     * @param int $id
     * @param array $columns
     * @return T|null
     */
    public function findFirst(int $id, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface;

    /**
     * @param array $where
     * @param array $columns
     * @return Collection<T>
     */
    public function findWhere(array $where, array $columns = self::DEFAULT_COLUMNS): Collection;

    /**
     * @param array $where
     * @param array $columns
     * @return T|null
     */
    public function findFirstWhere(array $where, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface;

    /**
     * @param string $field
     * @param string|null $value
     * @param array $columns
     * @return T|null
     */
    public function findFirstByField(
        string $field,
        string $value = null,
        array  $columns = ['*']
    ): ?DomainObjectInterface;

    /**
     * @param string $field
     * @param array $values
     * @param array $additionalWhere
     * @param array $columns
     * @return Collection<T>
     * @throws Exception
     */
    public function findWhereIn(string $field, array $values, array $additionalWhere = [], array $columns = self::DEFAULT_COLUMNS): Collection;

    /**
     * @param array $attributes
     * @return T
     */
    public function create(array $attributes): DomainObjectInterface;

    /**
     * @param array $inserts
     * @return bool
     */
    public function insert(array $inserts): bool;

    /**
     * @param int $id
     * @param DomainObjectInterface $domainObject
     * @return T
     */
    public function updateFromDomainObject(int $id, DomainObjectInterface $domainObject): DomainObjectInterface;

    /**
     * @param int $id
     * @param array $attributes
     * @return T
     */
    public function updateFromArray(int $id, array $attributes): DomainObjectInterface;

    /**
     * @param array $attributes
     * @param array $where
     * @return int Number of affected rows
     */
    public function updateWhere(array $attributes, array $where): int;

    /**
     * @param int $id
     * @param array $attributes
     * @param array $where
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
}
