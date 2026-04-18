<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\ContactAttributeDefinitionDomainObjectAbstract;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactAttributeDefinition extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            ContactAttributeDefinitionDomainObjectAbstract::OPTIONS => 'array',
            ContactAttributeDefinitionDomainObjectAbstract::IS_ACTIVE => 'boolean',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            ContactAttributeDefinitionDomainObjectAbstract::ACCOUNT_ID,
            ContactAttributeDefinitionDomainObjectAbstract::NAME,
            ContactAttributeDefinitionDomainObjectAbstract::LABEL,
            ContactAttributeDefinitionDomainObjectAbstract::TYPE,
            ContactAttributeDefinitionDomainObjectAbstract::OPTIONS,
            ContactAttributeDefinitionDomainObjectAbstract::SORT_ORDER,
            ContactAttributeDefinitionDomainObjectAbstract::IS_ACTIVE,
        ];
    }
}
