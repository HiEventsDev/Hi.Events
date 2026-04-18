<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\Models\ContactAttributeDefinition;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;

/**
 * @extends BaseRepository<ContactAttributeDefinitionDomainObject>
 */
class ContactAttributeDefinitionRepository extends BaseRepository implements ContactAttributeDefinitionRepositoryInterface
{
    public function getDomainObject(): string
    {
        return ContactAttributeDefinitionDomainObject::class;
    }

    protected function getModel(): string
    {
        return ContactAttributeDefinition::class;
    }
}
