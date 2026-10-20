<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\LifecycleEmailTemplateDomainObject;
use HiEvents\Models\LifecycleEmailTemplate;
use HiEvents\Repository\Interfaces\LifecycleEmailTemplateRepositoryInterface;

/**
 * @extends BaseRepository<LifecycleEmailTemplateDomainObject>
 */
class LifecycleEmailTemplateRepository extends BaseRepository implements LifecycleEmailTemplateRepositoryInterface
{
    protected function getModel(): string
    {
        return LifecycleEmailTemplate::class;
    }

    public function getDomainObject(): string
    {
        return LifecycleEmailTemplateDomainObject::class;
    }
}
