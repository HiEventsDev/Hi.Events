<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * This model points to a view: question_and_answer_view
 */
class QuestionAndAnswerView extends BaseModel
{
    protected $model = 'question_and_answer_view';

    protected function getCastMap(): array
    {
        return [
            'answer' => 'array',
            'question_options' => 'array',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
