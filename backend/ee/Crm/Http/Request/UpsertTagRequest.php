<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;

class UpsertTagRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'color' => ['nullable', ...RulesHelper::HEX_COLOR],
        ];
    }
}
