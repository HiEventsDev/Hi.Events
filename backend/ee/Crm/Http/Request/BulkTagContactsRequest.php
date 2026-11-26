<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Enums\CrmBulkAction;

class BulkTagContactsRequest extends BulkContactsRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'action' => ['required', 'string', 'in:'.CrmBulkAction::ADD_TAGS->value.','.CrmBulkAction::REMOVE_TAGS->value],
            'tag_ids' => ['required', 'array', 'size:1'],
            'tag_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'tag_ids.size' => __('Choose one tag at a time.'),
        ];
    }
}
