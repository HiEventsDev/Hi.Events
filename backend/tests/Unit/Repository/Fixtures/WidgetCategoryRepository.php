<?php

declare(strict_types=1);

namespace Tests\Unit\Repository\Fixtures;

use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<WidgetCategoryDomainObject>
 */
class WidgetCategoryRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return WidgetCategoryModel::class;
    }

    public function getDomainObject(): string
    {
        return WidgetCategoryDomainObject::class;
    }
}
