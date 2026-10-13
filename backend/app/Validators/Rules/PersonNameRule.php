<?php

namespace HiEvents\Validators\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonNameRule implements ValidationRule
{
    public const MAX_LENGTH = 50;

    private const DISALLOWED_PATTERNS = [
        '/[<>@{}]/u',
        '/https?:|www\./iu',
        '/\.(com|net|org|info|biz|io|co|ly|me|app|xyz|site|online|link|shop|top|click|live|store|ru|cn|tk)\b/iu',
        '/(\p{Nd}\P{Nd}{0,3}){5,}/u',
    ];

    public function __construct(
        private readonly ?string $currentValue = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === $this->currentValue) {
            return;
        }

        foreach (self::DISALLOWED_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                $fail(__('The :attribute may only contain letters, spaces and simple punctuation.'));

                return;
            }
        }
    }

    public static function maxLengthFor(?string $currentValue): int
    {
        return max(self::MAX_LENGTH, mb_strlen($currentValue ?? ''));
    }

    public static function rules(bool $required = true, ?string $currentValue = null): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:1',
            'max:'.self::maxLengthFor($currentValue),
            new self($currentValue),
        ];
    }
}
