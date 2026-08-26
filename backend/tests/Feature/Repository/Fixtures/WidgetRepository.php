<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Fixtures;

use HiEvents\Repository\Eloquent\BaseRepository;

/**
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
