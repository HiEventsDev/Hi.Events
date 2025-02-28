<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * This model points to a view: question_and_answer_view
 */
class QuestionAndAnswerView extends Model
{
    protected string $model = 'question_and_answer_view';

    protected $casts = [
        'answer' => 'array',
        'question_options' => 'array',
    ];

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
