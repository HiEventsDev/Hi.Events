<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\ContactDomainObjectAbstract;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            ContactDomainObjectAbstract::ATTRIBUTES => 'array',
            ContactDomainObjectAbstract::ATTRIBUTES_HISTORY => 'array',
            ContactDomainObjectAbstract::PROCESSED_QUESTION_ANSWER_IDS => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            ContactDomainObjectAbstract::ACCOUNT_ID,
            ContactDomainObjectAbstract::EMAIL,
            ContactDomainObjectAbstract::FIRST_NAME,
            ContactDomainObjectAbstract::LAST_NAME,
            ContactDomainObjectAbstract::ATTRIBUTES,
            ContactDomainObjectAbstract::ATTRIBUTES_HISTORY,
            ContactDomainObjectAbstract::PROCESSED_QUESTION_ANSWER_IDS,
        ];
    }
}
