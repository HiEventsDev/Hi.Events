<?php

declare(strict_types=1);

namespace Tests\Unit\Repository\Fixtures;

use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * Concrete BaseRepository subclass used by BaseRepositoryTest.
 *
 * Exists only in tests so we can exercise BaseRepository against an isolated
 * fixture table without coupling tests to any specific production entity.
 *
 * @extends BaseRepository<WidgetDomainObject>
 */
class WidgetRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return WidgetModel::class;
    }

    public function getDomainObject(): string
    {
        return WidgetDomainObject::class;
    }

    /**
     * Test hooks: expose protected state so we can assert reset behaviour
     * without resorting to reflection.
     */
    public function exposeEagerLoads(): array
    {
        return $this->eagerLoads;
    }

    public function exposeBuilderHasWheres(): bool
    {
        $base = $this->model->getQuery();

        return ! empty($base->wheres);
    }
}
