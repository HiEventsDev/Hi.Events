<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Validator;

class BulkContactsRequest extends BaseRequest
{
    public const MAX_SELECTED_IDS = 1000;

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:ids,filter'],
            'contact_ids' => ['required_if:mode,ids', 'prohibited_if:mode,filter', 'array', 'min:1', 'max:'.self::MAX_SELECTED_IDS],
            'contact_ids.*' => ['integer', 'min:1'],
            'query' => ['nullable', 'string', 'max:200'],
            'confirmation_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = $this->input('contact_ids');

            if (is_array($ids) && count($ids) !== count(array_unique($ids, SORT_REGULAR))) {
                $validator->errors()->add('contact_ids', __('Each contact can only be selected once.'));
            }
        });
    }

    /**
     * @return int[]|null
     */
    public function contactIds(): ?array
    {
        return $this->validated('mode') === 'ids'
            ? array_map('intval', $this->validated('contact_ids'))
            : null;
    }
}
