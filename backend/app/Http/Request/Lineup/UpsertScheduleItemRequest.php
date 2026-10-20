<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Validator;

class UpsertScheduleItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'event_space_id' => ['nullable', 'integer'],
            'lineup_entry_ids' => ['array', 'max:50'],
            'lineup_entry_ids.*' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $errors = $validator->errors();
            $entryIds = $this->input('lineup_entry_ids', []);
            $title = $this->input('title');

            if (! $errors->has('title') && trim(is_string($title) ? $title : '') === '' && (! is_array($entryIds) || $entryIds === [])) {
                $errors->add('title', __('Add a title or at least one person from the lineup'));
            }

            if (! $errors->hasAny(['lineup_entry_ids', 'lineup_entry_ids.*']) && is_array($entryIds) && count($entryIds) !== count(array_unique($entryIds))) {
                $errors->add('lineup_entry_ids', __('Each person may only be added once'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => __('The end time must be after the start time'),
        ];
    }
}
