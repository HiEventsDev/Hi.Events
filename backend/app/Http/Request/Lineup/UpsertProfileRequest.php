<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

use HiEvents\Http\Request\BaseRequest;

class UpsertProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:20000'],
            'links' => ['array', 'max:12'],
            'links.*' => ['required', 'string', 'url:https', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'links.*.url' => __('Each link must be a valid https:// URL'),
        ];
    }
}
