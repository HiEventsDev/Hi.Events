<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request\Rules;

use Closure;
use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Contracts\Validation\ValidationRule;

class NoLinksRule implements ValidationRule
{
    private const DISALLOWED_PATTERNS = [
        '/[<>]/u',
        '/https?:|www\./iu',
        PersonNameRule::LINKABLE_DOMAIN_PATTERN,
    ];

    public static function isSafe(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        foreach (self::DISALLOWED_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! self::isSafe($value)) {
            $fail(__('The :attribute can\'t contain links or web addresses.'));
        }
    }
}
