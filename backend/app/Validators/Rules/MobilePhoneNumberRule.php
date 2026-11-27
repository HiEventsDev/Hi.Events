<?php

namespace HiEvents\Validators\Rules;

use Closure;
use HiEvents\DomainObjects\Enums\PhoneCollection;
use HiEvents\Services\Infrastructure\Phone\PhoneNumberNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;

class MobilePhoneNumberRule implements ValidationRule
{
    public const MAX_INPUT_LENGTH = 30;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || (new PhoneNumberNormalizer)->toMobileE164($value) === null) {
            $fail(__('Enter a valid mobile number, including the country code.'));
        }
    }

    public static function rules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:'.self::MAX_INPUT_LENGTH,
            new self,
        ];
    }

    public static function rulesFor(PhoneCollection $collection): array
    {
        return $collection->isCollected()
            ? self::rules($collection === PhoneCollection::REQUIRED)
            : [];
    }
}
