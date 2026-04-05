<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\DocumentTemplateDomainObjectAbstract;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            DocumentTemplateDomainObjectAbstract::SETTINGS => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            DocumentTemplateDomainObjectAbstract::ACCOUNT_ID,
            DocumentTemplateDomainObjectAbstract::EVENT_ID,
            DocumentTemplateDomainObjectAbstract::NAME,
            DocumentTemplateDomainObjectAbstract::TYPE,
            DocumentTemplateDomainObjectAbstract::CONTENT,
            DocumentTemplateDomainObjectAbstract::SETTINGS,
            DocumentTemplateDomainObjectAbstract::IS_DEFAULT,
        ];
    }
}
