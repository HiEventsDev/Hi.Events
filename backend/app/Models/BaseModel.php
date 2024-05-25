<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Builder
 */
abstract class BaseModel extends Model
{
    use SoftDeletes;

    /** @var array */
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $this->fillable = $this->getFillableFields();
        $this->casts = $this->getCastMap();
        $this->timestamps = $this->getTimestampsEnabled();

        parent::__construct($attributes);
    }

    abstract protected function getCastMap(): array;

    abstract protected function getFillableFields(): array;

    protected function getTimestampsEnabled(): bool
    {
        return true;
    }
}
