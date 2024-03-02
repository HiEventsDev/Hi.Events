<?php

namespace HiEvents\Validators\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InsensitiveIn implements ValidationRule
{
    protected array $values;

    public function __construct(array $values)
    {
        $this->values = array_map('strtoupper', $values);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!in_array(strtoupper($value), $this->values, true)) {
            $fail(':attribute must be one of ' . implode(', ', $this->values));
        }
    }
}
