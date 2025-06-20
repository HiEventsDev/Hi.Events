<?php

namespace HiEvents\DataTransferObjects;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

abstract class BaseDataObject extends Data
{
    /**
     * Check if a property was explicitly provided in the input data (even if the value is null).
     * Useful for partial update.
     */
    public function wasProvided(string $propertyName): bool
    {
        return property_exists($this, $propertyName) && !($this->{$propertyName} instanceof Optional);
    }

    /**
     * Get the value of a provided property, or return default if not provided
     */
    public function getProvided(string $propertyName, mixed $default = null): mixed
    {
        return $this->wasProvided($propertyName) ? $this->{$propertyName} : $default;
    }
}
