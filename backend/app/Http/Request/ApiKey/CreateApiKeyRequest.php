<?php

namespace HiEvents\Http\Request\ApiKey;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use Illuminate\Validation\Rule;

class CreateApiKeyRequest extends BaseRequest
{
    public const NAME_PATTERN = "/^[\pL\pN][\pL\pN\s\-_.,:'&()\/]*$/u";

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', 'regex:'.self::NAME_PATTERN],
            'access' => ['required', Rule::enum(ApiKeyAccess::class)],
            'expires_in_days' => ['present', 'nullable', 'integer', Rule::in(ApiKeyService::EXPIRY_OPTIONS_IN_DAYS)],
            'password' => ['nullable', 'string', 'max:255'],
            'reauth_proof' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('Use letters, numbers, spaces and basic punctuation in the name.'),
            'expires_in_days.in' => __('Choose one of the available expiry options.'),
        ];
    }
}
