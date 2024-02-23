<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\QuestionAndAnswerViewDomainObject;
use TicketKitten\Models\QuestionAndAnswerView;
use TicketKitten\Repository\Interfaces\QuestionAndAnswerViewRepositoryInterface;

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
