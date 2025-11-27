<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends BaseModel
{
    use SoftDeletes;

    public function getDomainObjectClass(): string
    {
        return EmailTemplateDomainObject::class;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getCastMap(): array
    {
        return [
            'cta' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getFillableFields(): array
    {
        return [
            'account_id',
            'organizer_id',
            'event_id',
            'template_type',
            'subject',
            'body',
            'cta',
            'engine',
            'is_active',
        ];
    }
}
