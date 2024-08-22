<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use BadMethodCallException;
use Carbon\Carbon;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\BaseModel;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @template T
 * @implements RepositoryInterface<T>
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Model|BaseModel|Builder $model;

    protected Application $app;

    protected DatabaseManager $db;

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
     *
     * @return string
     */
    abstract protected function getModel(): string;

    public function all(array $columns = self::DEFAULT_COLUMNS): Collection
    {
        return $this->handleResults($this->model->all($columns));
    }

    public function paginate(
        int   $limit = null,
        array $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator
    {
        $results = $this->model->paginate($this->getPaginationPerPage($limit), $columns);
        $this->resetModel();

        return $this->handleResults($results);
    }

    public function paginateWhere(
        array $where,
        int   $limit = null,
        array $columns = self::DEFAULT_COLUMNS,
        int   $page = null,
    ): LengthAwarePaginator
    {
        $this->applyConditions($where);
        $results = $this->model->paginate(
            perPage: $this->getPaginationPerPage($limit),
            columns: $columns,
            page: $page,
        );
        $this->resetModel();

        return $this->handleResults($results);
    }

    public function simplePaginateWhere(
        array $where,
        int   $limit = null,
        array $columns = self::DEFAULT_COLUMNS,
    ): Paginator
    {
        $this->applyConditions($where);
        $results = $this->model->simplePaginate($this->getPaginationPerPage($limit), $columns);
        $this->resetModel();

        return $this->handleResults($results);
    }

    public function paginateEloquentRelation(
        Relation $relation,
        int      $limit = null,
        array    $columns = self::DEFAULT_COLUMNS
    ): LengthAwarePaginator
    {
        return $this->handleResults($relation->paginate($this->getPaginationPerPage($limit), $columns));
    }

    public function findById(int $id, array $columns = self::DEFAULT_COLUMNS): DomainObjectInterface
    {
        return $this->handleSingleResult($this->model->findOrFail($id, $columns));
    }

    public function findFirstByField(
        string $field,
        string $value = null,
        array  $columns = ['*']
    ): ?DomainObjectInterface
    {
        $model = $this->model->where($field, '=', $value)->firstOrFail($columns);
        $this->resetModel();

        return $this->handleSingleResult($model);
    }

    public function findFirst(int $id, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface
    {
        return $this->handleSingleResult($this->model->findOrFail($id, $columns));
    }

    public function findWhere(array $where, array $columns = self::DEFAULT_COLUMNS): Collection
    {
        $this->applyConditions($where);
        $model = $this->model->get($columns);
        $this->resetModel();

        return $this->handleResults($model);
    }

    public function findFirstWhere(array $where, array $columns = self::DEFAULT_COLUMNS): ?DomainObjectInterface
    {
        $this->applyConditions($where);
        $model = $this->model->first($columns);
        $this->resetModel();

        return $this->handleSingleResult($model);
    }

    public function findWhereIn(string $field, array $values, array $additionalWhere = [], array $columns = self::DEFAULT_COLUMNS): Collection
    {
        if ($additionalWhere) {
            $this->applyConditions($additionalWhere);
        }

        $model = $this->model->whereIn($field, $values)->get($columns);
        $this->resetModel();

        return $this->handleResults($model);
    }

    public function create(array $attributes): DomainObjectInterface
    {
        $model = $this->model->newInstance(collect($attributes)->toArray());
        $model->save();
        $this->resetModel();

        return $this->handleSingleResult($model);
    }

    public function insert(array $inserts): bool
    {
        // When doing a bulk insert Eloquent doesn't autofill the updated/created dates,
        // so we need to do it manually
        foreach ($inserts as $index => $insert) {
            if (!isset($insert['created_at'], $insert['updated_at'])) {
                $now = Carbon::now();
                $inserts[$index]['created_at'] = $now;
                $inserts[$index]['updated_at'] = $now;
            }
        }
        $insert = $this->model->insert($inserts);
        $this->resetModel();

        return $insert;
    }

    public function updateFromDomainObject(int $id, DomainObjectInterface $domainObject): DomainObjectInterface
    {
        return $this->updateFromArray($id, $domainObject->toArray());
    }

    public function updateFromArray(int $id, array $attributes): DomainObjectInterface
    {
        $model = $this->model->findOrFail($id);
        $model->fill($attributes);
        $model->save();
        $this->resetModel();

        return $this->handleSingleResult($model);
    }

    public function updateWhere(array $attributes, array $where): int
    {
        $this->applyConditions($where);
        $count = $this->model->update($attributes);
        $this->resetModel();

        return $count;
    }

    public function updateByIdWhere(int $id, array $attributes, array $where): DomainObjectInterface
    {
        $model = $this->model->where($where)->findOrFail($id);
        $model->update($attributes);
        $this->resetModel();

        return $this->handleSingleResult($model);
    }

    public function deleteById(int $id): bool
    {
        return $this->model->findOrFail($id)->delete();
    }

    public function incrementEach(array $columns, array $additionalUpdates = [], ?array $where = null): int
    {
        if ($where) {
            $this->applyConditions($where);
        }

        $count = $this->model->incrementEach($columns, $additionalUpdates);
        $this->resetModel();

        return $count;
    }

    public function decrementEach(array $where, array $columns, array $extra = []): int
    {
        $this->applyConditions($where);
        $count = $this->model->decrementEach($columns, $extra);
        $this->resetModel();

        return $count;
    }

    public function increment(int|float $id, string $column, int|float $amount = 1): int
    {
        return $this->model->findOrFail($id)->increment($column, $amount);
    }

    public function incrementWhere(array $where, string $column, int|float $amount = 1): int
    {
        $this->applyConditions($where);
        $count = $this->model->increment($column, $amount);
        $this->resetModel();

        return $count;
    }

    public function decrement(int|float $id, string $column, int|float $amount = 1): int
    {
        return $this->model->findOrFail($id)?->decrement($column, $amount);
    }

    public function deleteWhere(array $conditions): int
    {
        $this->applyConditions($conditions);
        $deleted = $this->model->delete();
        $this->resetModel();

        return $deleted;
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

    protected function applyConditions(array $where): void
    {
        foreach ($where as $field => $value) {
            if (is_callable($value) && !is_string($value)) {
                $this->model = $this->model->where($value);
            } elseif (is_array($value)) {
                [$field, $condition, $val] = $value;
                $this->model = $this->model->where($field, $condition, $val);
            } else {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }

    protected function initModel(string $model = null): Model
    {
        return $this->app->make($model ?: $this->getModel());
    }

    protected function handleResults($results, string $domainObjectOverride = null)
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
        string     $domainObjectOverride = null
    ): ?DomainObjectInterface
    {
        if (!$model) {
            return null;
        }

        return $this->hydrateDomainObjectFromModel($model, $domainObjectOverride);
    }

    protected function applyFilterFields(QueryParamsDTO $params, array $allowedFilterFields = []): void
    {
        if ($params->filter_fields && $params->filter_fields->isNotEmpty()) {
            $params->filter_fields->each(function ($filterField) use ($allowedFilterFields) {
                if (!in_array($filterField->field, $allowedFilterFields, true)) {
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
                ];

                $operator = $operatorMapping[$filterField->operator] ?? throw new BadMethodCallException(
                    sprintf('Operator %s is not supported', $filterField->operator)
                );

                $this->model = $this->model->where(
                    column: $filterField->field,
                    operator: $operator,
                    value: $isNull ? null : $filterField->value,
                );
            });
        }
    }

    protected function resetModel(): void
    {
        $model = $this->getModel();
        $this->model = new $model();
    }

    private function getPaginationPerPage(?int $perPage): int
    {
        if (is_null($perPage)) {
            $perPage = self::DEFAULT_PAGINATE_LIMIT;
        }

        return (int)min($perPage, self::MAX_PAGINATE_LIMIT);
    }

    /**
     * @param Model $model
     * @param string|null $domainObjectOverride A FQCN of a DO
     * @param array|null $relationships
     * @return DomainObjectInterface
     *
     * @todo use hydrate method from AbstractDomainObject
     */
    private function hydrateDomainObjectFromModel(
        Model  $model,
        string $domainObjectOverride = null,
        ?array $relationships = null,
    ): DomainObjectInterface
    {
        /** @var DomainObjectInterface $object */
        $object = $domainObjectOverride ?: $this->getDomainObject();
        $object = new $object();

        foreach ($model->attributesToArray() as $attribute => $value) {
            $method = 'set' . ucfirst(Str::camel($attribute));
            if (is_callable(array($object, $method))) {
                $object->$method($value);
            }
        }

        $this->handleEagerLoads($model, $object, $relationships);

        return $object;
    }

    /**
     * This method will handle nested eager loading of relationships. It works, but it's not pretty.
     *
     * @param Model $model
     * @param DomainObjectInterface $object
     * @param Relationship[]|null $relationships
     *
     * @return void
     */
    private function handleEagerLoads(Model $model, DomainObjectInterface $object, ?array $relationships): void
    {
        $eagerLoads = $relationships ?: $this->eagerLoads;

        foreach ($eagerLoads as $eagerLoad) {
            if (!$model->relationLoaded($eagerLoad->getName())) {
                continue;
            }
            $relatedModels = $model->getRelation($eagerLoad->getName());
            $setterMethod = 'set' . Str::studly($eagerLoad->getName());

            if (!is_callable([$object, $setterMethod])) {
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
            } else if ($relatedModels instanceof BaseModel) {
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
