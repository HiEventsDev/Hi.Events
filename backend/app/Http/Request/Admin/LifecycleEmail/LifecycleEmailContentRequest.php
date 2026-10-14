<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Admin\LifecycleEmail;

use HiEvents\Http\Request\BaseRequest;

class LifecycleEmailContentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'cta_label' => ['required', 'string', 'max:100'],
        ];
    }
}
