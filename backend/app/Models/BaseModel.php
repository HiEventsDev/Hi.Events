<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 */
abstract class BaseModel extends Model
{
    /** @var array */
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $this->fillable = $this->getFillableFields();
        $this->casts = $this->getCastMap();
        $this->timestamps = $this->getTimestampsEnabled();

        parent::__construct($attributes);
    }

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields() : array
    {
        return [];
    }

    protected function getTimestampsEnabled(): bool
    {
        return true;
    }
}
