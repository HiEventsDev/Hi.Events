<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use BadMethodCallException;
use Carbon\Carbon;
use Closure;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\BaseModel;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TypeError;

/**
 * @template T of DomainObjectInterface
 *
 * @implements RepositoryInterface<T>
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Model|BaseModel|Builder $model;

    protected Application $app;

    protected DatabaseManager $db;

    protected int $maxPerPage = self::MAX_PAGINATE_LIMIT;

    /** @var Relationship[] */
    protected array $eagerLoads = [];

    public function __construct(Application $application, DatabaseManager $db)
    {
        $this->app = $application;
        $this->model = $this->initModel();
        $this->db = $db;
    }

    /**
     * Returns a FQCL of the model
     */
    abstract protected function getModel(): string;

    /**
     * @param  class-string<IsSortable>  $domainObjectClass
     */
    protected function validateSortColumn(?string $sortBy, string $domainObjectClass): string
    {
        $allowedColumns = array_keys($domainObjectClass::getAllowedSorts()->toArray());
        $default = $domainObjectClass::getDefaultSort();

        if ($sortBy === null || ! in_array($sortBy, $allowedColumns, true)) {
            return $default;
        }

        return $sortBy;
    }

    protected function validateSortDirection(?string $sortDirection, string $domainObjectClass): string
    {
        return in_array(strtolower($sortDirection ?? ''), ['asc', 'desc'], true)
            ? $sortDirection
            : $domainObjectClass::getDefaultSortDirection();
    }

    public function setMaxPerPage(int $maxPerPage): static
    {
        $this->maxPerPage = $maxPerPage;

        return $this;
    }

    public function all(array $columns = self::DEFAULT_COLUMNS): Collection
    {
        return $this->runQuery(
            fn () => $this->handleResults($this->model->all($columns))
        );
    }

    public function paginate(
        ?int $limit = null,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator {
        return $this->runQuery(
            fn () => $this->handleResults(
                $this->model->paginate($this->getPaginationPerPage($limit), $columns)
            )
        );
    }

    public function paginateWhere(
        array $where,
        ?int $limit = null,
        array $columns = self::DEFAULT_COLUMNS,
        ?int $page = null,
    ): LengthAwarePaginator {
        return $this->runQuery(function () use ($where, $limit, $columns, $page) {
            $this->applyConditions($where);

            return $this->handleResults($this->model->paginate(
                perPage: $this->getPaginationPerPage($limit),
                columns: $columns,
                page: $page,
            ));
        });
    }

    public function simplePaginateWhere(
        array $where,
        ?int $limit = null,
        array $columns = self::DEFAULT_COLUMNS,
    ): Paginator {
        return $this->runQuery(function () use ($where, $limit, $columns) {
            $this->applyConditions($where);

            return $this->handleResults(
                $this->model->simplePaginate($this->getPaginationPerPage($limit), $columns)
            );
        });
    }

    public function paginateEloquentRelation(
        Relation $relation,
        ?int $limit = null,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator {
        return $this->runQuery(
            fn () => $this->handleResults(
                $relation->paginate($this->getPaginationPerPage($limit), $columns)
            )
        );
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findById(int $id, array $columns = self::DEFAULT_COLUMNS): DomainObjectInterface
    {
        return $this->runQuery(
            fn () => $this->handleSingleResult($this->model->findOrFail($id, $columns))
        );
    }

    public function findFirstByField(
        string $field,
        ?string $value = null,
        array $columns = ['*']
    ): ?DomainObjectInterface {
        return $this->runQuery(
            fn () => $this->handleSingleResult(
                $this->model->where($field, '=', $value)->first($columns)
            )
        );
    }

    public function findFirst(int $id, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface
    {
        return $this->runQuery(
            fn () => $this->handleSingleResult($this->model->findOrFail($id, $columns))
        );
    }

    public function findWhere(
        array $where,
        array $columns = self::DEFAULT_COLUMNS,
        array $orderAndDirections = [],
    ): Collection {
        return $this->runQuery(function () use ($where, $columns, $orderAndDirections) {
            $this->applyConditions($where);

            foreach ($orderAndDirections as $orderAndDirection) {
                $this->model = $this->model->orderBy(
                    $orderAndDirection->getOrder(),
                    $orderAndDirection->getDirection()
                );
            }

            return $this->handleResults($this->model->get($columns));
        });
    }

    public function findFirstWhere(array $where, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface
    {
        return $this->runQuery(function () use ($where, $columns) {
            $this->applyConditions($where);

            return $this->handleSingleResult($this->model->first($columns));
        });
    }

    public function findWhereIn(string $field, array $values, array $additionalWhere = [], array $columns = self::DEFAULT_COLUMNS): Collection
    {
        return $this->runQuery(function () use ($field, $values, $additionalWhere, $columns) {
            if ($additionalWhere) {
                $this->applyConditions($additionalWhere);
            }

            return $this->handleResults($this->model->whereIn($field, $values)->get($columns));
        });
    }

    public function create(array $attributes): DomainObjectInterface
    {
        return $this->runQuery(function () use ($attributes) {
            $model = $this->model->newInstance(collect($attributes)->toArray());
            $model->save();

            return $this->handleSingleResult($model);
        });
    }

    public function insert(array $inserts): bool
    {
        return $this->runQuery(function () use ($inserts) {
            // When doing a bulk insert Eloquent doesn't autofill the updated/created dates,
            // so we need to do it manually
            foreach ($inserts as $index => $insert) {
                if (! isset($insert['created_at'], $insert['updated_at'])) {
                    $now = Carbon::now();
                    $inserts[$index]['created_at'] = $now;
                    $inserts[$index]['updated_at'] = $now;
                }
            }

            return $this->model->insert($inserts);
        });
    }

    public function updateFromDomainObject(int $id, DomainObjectInterface $domainObject): DomainObjectInterface
    {
        return $this->updateFromArray($id, $domainObject->toArray());
    }

    public function updateFromArray(int $id, array $attributes): DomainObjectInterface
    {
        return $this->runQuery(function () use ($id, $attributes) {
            $model = $this->model->findOrFail($id);
            $model->fill($attributes);
            $model->save();

            return $this->handleSingleResult($model);
        });
    }

    public function updateWhere(array $attributes, array $where): int
    {
        return $this->runQuery(function () use ($attributes, $where) {
            $this->applyConditions($where);

            return $this->model->update($attributes);
        });
    }

    public function updateByIdWhere(int $id, array $attributes, array $where): DomainObjectInterface
    {
        return $this->runQuery(function () use ($id, $attributes, $where) {
            $model = $this->model->where($where)->findOrFail($id);
            $model->update($attributes);

            return $this->handleSingleResult($model);
        });
    }

    public function deleteById(int $id): bool
    {
        return $this->runQuery(
            fn () => (bool) $this->model->findOrFail($id)->delete()
        );
    }

    public function incrementEach(array $columns, array $additionalUpdates = [], ?array $where = null): int
    {
        return $this->runQuery(function () use ($columns, $additionalUpdates, $where) {
            if ($where) {
                $this->applyConditions($where);
            }

            // Eloquent\Builder's __call swallows incrementEach's int return value
            // and hands back the Builder, so we route through the underlying
            // QueryBuilder to get the affected-row count.
            return $this->resolveBaseQuery()->incrementEach($columns, $additionalUpdates);
        });
    }

    public function decrementEach(array $where, array $columns, array $extra = []): int
    {
        return $this->runQuery(function () use ($where, $columns, $extra) {
            $this->applyConditions($where);

            return $this->resolveBaseQuery()->decrementEach($columns, $extra);
        });
    }

    public function increment(int|float $id, string $column, int|float $amount = 1): int
    {
        return $this->runQuery(
            fn () => $this->model->findOrFail($id)->increment($column, $amount)
        );
    }

    public function incrementWhere(array $where, string $column, int|float $amount = 1): int
    {
        return $this->runQuery(function () use ($where, $column, $amount) {
            $this->applyConditions($where);

            return $this->model->increment($column, $amount);
        });
    }

    public function decrement(int|float $id, string $column, int|float $amount = 1): int
    {
        return $this->runQuery(
            fn () => $this->model->findOrFail($id)->decrement($column, $amount)
        );
    }

    public function deleteWhere(array $conditions): int
    {
        return $this->runQuery(function () use ($conditions) {
            $this->applyConditions($conditions);

            return $this->model->delete();
        });
    }

    public function countWhere(array $conditions): int
    {
        return $this->runQuery(function () use ($conditions) {
            $this->applyConditions($conditions);

            return $this->model->count();
        });
    }

    public function loadRelation(string|Relationship $relationship): static
    {
        if (is_string($relationship)) {
            $relationship = new Relationship($relationship);
        }

        $this->eagerLoads[] = $relationship;
        $this->model = $this->model->with($relationship->buildLaravelEagerLoadArray());

        return $this;
    }

    public function includeDeleted(): static
    {
        $this->model = $this->model->withTrashed();

        return $this;
    }

    protected function applyConditions(array $where): void
    {
        foreach ($where as $field => $value) {
            if (is_callable($value) && ! is_string($value)) {
                $this->model = $this->model->where($value);
            } elseif (is_array($value)) {
                [$field, $condition, $val] = $value;
                $condition = strtolower($condition);

                switch ($condition) {
                    case 'in':
                        if (is_array($val)) {
                            $this->model = $this->model->whereIn($field, $val);
                        }
                        break;

                    case 'not in':
                        if (is_array($val)) {
                            $this->model = $this->model->whereNotIn($field, $val);
                        }
                        break;

                    case 'null':
                        $this->model = $this->model->whereNull($field);
                        break;

                    case 'not null':
                        $this->model = $this->model->whereNotNull($field);
                        break;

                    default:
                        $this->model = $this->model->where($field, $condition, $val);
                        break;
                }
            } else {
                // Simple equality condition
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }

    protected function initModel(?string $model = null): Model
    {
        return $this->app->make($model ?: $this->getModel());
    }

    /**
     * Execute a query callback and guarantee per-call state is reset afterwards,
     * even if the callback throws. This is the single point at which the in-flight
     * builder ($this->model) and the eager-load list ($this->eagerLoads) are cleared.
     *
     * The callback runs BEFORE reset, so hydration helpers that read $this->eagerLoads
     * (e.g. handleEagerLoads()) still see the correct state.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function runQuery(Closure $callback): mixed
    {
        try {
            return $callback();
        } finally {
            $this->resetState();
        }
    }

    protected function resetState(): void
    {
        $model = $this->getModel();
        $this->model = new $model;
        $this->eagerLoads = [];
    }

    /**
     * Resolve $this->model (which may be a fresh Model or an Eloquent Builder
     * after applyConditions()) to the underlying query builder. Required for
     * methods Eloquent\Builder::__call swallows the return value of, e.g.
     * incrementEach() / decrementEach().
     */
    private function resolveBaseQuery(): QueryBuilder
    {
        return $this->model instanceof Builder
            ? $this->model->getQuery()
            : $this->model->newQuery()->getQuery();
    }

    protected function handleResults($results, ?string $domainObjectOverride = null)
    {
        $domainObjects = [];
        foreach ($results as $result) {
            if ($result && $domainObject = $this->handleSingleResult($result, $domainObjectOverride)) {
                $domainObjects[] = $domainObject;
            }
        }

        if ($results instanceof LengthAwarePaginator) {
            return $results->setCollection(collect($domainObjects));
        }

        if ($results instanceof Paginator) {
            return $results->setCollection(collect($domainObjects));
        }

        return collect($domainObjects);
    }

    protected function handleSingleResult(
        ?BaseModel $model,
        ?string $domainObjectOverride = null
    ): ?DomainObjectInterface {
        if (! $model) {
            return null;
        }

        return $this->hydrateDomainObjectFromModel($model, $domainObjectOverride);
    }

    protected function applyFilterFields(
        QueryParamsDTO $params,
        array $allowedFilterFields = [],
        ?string $prefix = null,
    ): void {
        if ($params->filter_fields && $params->filter_fields->isNotEmpty()) {
            $params->filter_fields->each(function ($filterField) use ($prefix, $allowedFilterFields) {
                if (! in_array($filterField->field, $allowedFilterFields, true)) {
                    return;
                }

                $isNull = $filterField->value === 'null';

                $operatorMapping = [
                    'eq' => $isNull ? 'IS' : '=',
                    'ne' => $isNull ? 'IS NOT' : '!=',
                    'lt' => '<',
                    'lte' => '<=',
                    'gt' => '>',
                    'gte' => '>=',
                    'like' => 'LIKE',
                    'in' => 'IN',
                ];

                $operator = $operatorMapping[$filterField->operator] ?? throw new BadMethodCallException(
                    sprintf('Operator %s is not supported', $filterField->operator)
                );

                $field = $prefix ? $prefix.'.'.$filterField->field : $filterField->field;

                // Special handling for IN operator
                if ($operator === 'IN') {
                    // Ensure value is array or convert comma-separated string to array
                    $value = is_array($filterField->value)
                        ? $filterField->value
                        : explode(',', $filterField->value);

                    $this->model = $this->model->whereIn(
                        column: $field,
                        values: $value
                    );
                } else {
                    $this->model = $this->model->where(
                        column: $field,
                        operator: $operator,
                        value: $isNull ? null : $filterField->value,
                    );
                }
            });
        }
    }

    /**
     * @deprecated Use resetState() instead. Kept for backwards compatibility with
     *             subclass repositories that build custom queries on $this->model.
     */
    protected function resetModel(): void
    {
        $this->resetState();
    }

    private function getPaginationPerPage(?int $perPage): int
    {
        if (is_null($perPage)) {
            $perPage = self::DEFAULT_PAGINATE_LIMIT;
        }

        return (int) min($perPage, $this->maxPerPage);
    }

    /**
     * @param  string|null  $domainObjectOverride  A FQCN of a DO
     *
     * @todo use hydrate method from AbstractDomainObject
     */
    private function hydrateDomainObjectFromModel(
        Model $model,
        ?string $domainObjectOverride = null,
        ?array $relationships = null,
    ): DomainObjectInterface {
        /** @var DomainObjectInterface $object */
        $object = $domainObjectOverride ?: $this->getDomainObject();
        $object = new $object;

        foreach ($model->attributesToArray() as $attribute => $value) {
            $method = 'set'.Str::studly($attribute);
            if (is_callable([$object, $method])) {
                try {
                    $object->$method($value);
                } catch (TypeError $e) {
                    throw new TypeError(
                        sprintf(
                            'Type error when calling %s::%s with value %s: %s',
                            get_class($object),
                            $method,
                            var_export($value, true),
                            $e->getMessage()
                        ),
                        (int) $e->getCode(),
                        $e
                    );
                }

            }
        }

        $this->handleEagerLoads($model, $object, $relationships);

        return $object;
    }

    /**
     * This method will handle nested eager loading of relationships
     *
     * @param  Relationship[]|null  $relationships
     */
    private function handleEagerLoads(Model $model, DomainObjectInterface $object, ?array $relationships): void
    {
        $eagerLoads = $relationships ?: $this->eagerLoads;

        foreach ($eagerLoads as $eagerLoad) {
            if (! $model->relationLoaded($eagerLoad->getName())) {
                continue;
            }
            $relatedModels = $model->getRelation($eagerLoad->getName());
            $setterMethod = 'set'.Str::studly($eagerLoad->getName());

            if (! is_callable([$object, $setterMethod])) {
                throw new BadMethodCallException(
                    sprintf(
                        'Method %s is not callable on %s. Does it exist?',
                        $setterMethod,
                        get_class($object),
                    )
                );
            }

            if ($relatedModels instanceof Collection) {
                $relatedDomainObjects = $relatedModels->map(function ($relatedModel) use ($eagerLoad) {
                    return $this->hydrateDomainObjectFromModel(
                        $relatedModel,
                        $eagerLoad->getDomainObject(),
                        $eagerLoad->getNested(),
                    );
                });
                $object->$setterMethod($relatedDomainObjects);
            } elseif ($relatedModels instanceof BaseModel) {
                $relatedDomainObject = $this->hydrateDomainObjectFromModel(
                    $relatedModels,
                    $eagerLoad->getDomainObject(),
                    $eagerLoad->getNested(),
                );

                $object->$setterMethod($relatedDomainObject);
            }
        }
    }
}
