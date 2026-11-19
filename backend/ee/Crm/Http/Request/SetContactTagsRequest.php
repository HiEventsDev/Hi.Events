<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class SetContactTagsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tag_ids' => ['present', 'array', 'max:50'],
            'tag_ids.*' => ['integer', 'min:1'],
        ];
    }
}
