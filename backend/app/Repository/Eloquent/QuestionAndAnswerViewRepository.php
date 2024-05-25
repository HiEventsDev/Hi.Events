<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Models\QuestionAndAnswerView;
use HiEvents\Repository\Interfaces\QuestionAndAnswerViewRepositoryInterface;

class QuestionAndAnswerViewRepository extends BaseRepository implements QuestionAndAnswerViewRepositoryInterface
{
    protected function getModel(): string
    {
        return QuestionAndAnswerView::class;
    }

    public function getDomainObject(): string
    {
        return QuestionAndAnswerViewDomainObject::class;
    }
}
