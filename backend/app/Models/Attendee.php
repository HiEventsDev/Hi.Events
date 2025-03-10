<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendee extends BaseModel
{
    use SoftDeletes;

    public function question_and_answer_views(): HasMany
    {
        return $this->hasMany(QuestionAndAnswerView::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function check_in(): HasOne
    {
        return $this->hasOne(AttendeeCheckIn::class);
    }
}
