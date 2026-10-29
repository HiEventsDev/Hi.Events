<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Admin\LifecycleEmail;

class UpdateLifecycleEmailRequest extends LifecycleEmailContentRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'enabled' => ['required', 'boolean'],
        ];
    }
}
