<?php

namespace HiEvents\Validators\Rules;

use Closure;
use HiEvents\Exceptions\UnsafeWebhookUrlException;
use HiEvents\Services\Infrastructure\Webhook\WebhookUrlValidator;
use Illuminate\Contracts\Validation\ValidationRule;

class NoInternalUrlRule implements ValidationRule
{
    public function __construct(
        private readonly ?WebhookUrlValidator $validator = null,
    )
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail(__('The :attribute must be a valid URL.'));
            return;
        }

        try {
            ($this->validator ?? app(WebhookUrlValidator::class))->validate($value);
        } catch (UnsafeWebhookUrlException $exception) {
            $fail($exception->getMessage());
        }
    }
}
