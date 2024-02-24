<?php

namespace HiEvents\Repository\Eloquent;


use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\Models\QuestionAnswer;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;

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
