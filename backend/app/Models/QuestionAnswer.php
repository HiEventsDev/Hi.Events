<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;

class QuestionAnswer extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            QuestionAnswerDomainObjectAbstract::ANSWER => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            QuestionAnswerDomainObjectAbstract::QUESTION_ID,
            QuestionAnswerDomainObjectAbstract::TICKET_ID,
            QuestionAnswerDomainObjectAbstract::ORDER_ID,
            QuestionAnswerDomainObjectAbstract::ATTENDEE_ID,
            QuestionAnswerDomainObjectAbstract::ANSWER,
        ];
    }
}
