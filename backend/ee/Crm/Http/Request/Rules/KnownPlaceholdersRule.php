<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request\Rules;

use Closure;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenCatalog;
use Illuminate\Contracts\Validation\ValidationRule;

class KnownPlaceholdersRule implements ValidationRule
{
    public function __construct(
        private readonly bool $allowsEventPlaceholders,
        private readonly bool $isTemplate = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $error = is_string($value) ? CampaignTokenCatalog::firstError($value, $this->allowsEventPlaceholders, $this->isTemplate) : null;

        if ($error !== null) {
            $fail($error);
        }
    }
}
