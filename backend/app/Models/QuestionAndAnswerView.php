<?php

namespace TicketKitten\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * This model points to a view: question_and_answer_view
 */
class QuestionAndAnswerView extends Model
{
    protected $model = 'question_and_answer_view';

    protected $casts = [
        'answer' => 'array',
    ];
}
