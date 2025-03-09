<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionAnswer extends BaseModel
{
    use SoftDeletes;

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
            QuestionAnswerDomainObjectAbstract::PRODUCT_ID,
            QuestionAnswerDomainObjectAbstract::ORDER_ID,
            QuestionAnswerDomainObjectAbstract::ATTENDEE_ID,
            QuestionAnswerDomainObjectAbstract::ANSWER,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
