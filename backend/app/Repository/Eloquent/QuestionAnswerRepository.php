<?php

namespace TicketKitten\Repository\Eloquent;


use TicketKitten\DomainObjects\QuestionAnswerDomainObject;
use TicketKitten\Models\QuestionAnswer;
use TicketKitten\Repository\Interfaces\QuestionAnswerRepositoryInterface;

class QuestionAnswerRepository extends BaseRepository implements QuestionAnswerRepositoryInterface
{
    protected function getModel(): string
    {
        return QuestionAnswer::class;
    }

    public function getDomainObject(): string
    {
        return QuestionAnswerDomainObject::class;
    }
}
