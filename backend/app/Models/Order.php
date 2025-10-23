<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends BaseModel
{
    use SoftDeletes;

    public function question_and_answer_views(): HasMany
    {
        return $this->hasMany(QuestionAndAnswerView::class);
    }

    public function stripe_payment(): HasOne
    {
        return $this->hasOne(StripePayment::class);
    }

    public function order_items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('created_at', 'desc');
    }

    public function order_application_fee(): HasOne
    {
        return $this->hasOne(OrderApplicationFee::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    protected function getCastMap(): array
    {
        return [
            'total_before_additions' => 'float',
            'total_tax' => 'float',
            'total_gross' => 'float',
            'total_discount' => 'float',
            'total_fee' => 'float',
            'total_refunded' => 'float',
            'point_in_time_data' => 'array',
            'address' => 'array',
            'taxes_and_fees_rollup' => 'array',
            'statistics_decremented_at' => 'datetime'
        ];
    }
}
