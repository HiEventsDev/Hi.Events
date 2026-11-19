<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Status\CrmSignupFormStatus;
use HiEvents\Enterprise\Crm\Http\Request\Rules\NoLinksRule;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertSignupFormRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'status' => ['required', 'string', Rule::in(CrmSignupFormStatus::valuesArray())],
            'title' => ['required', 'string', 'min:1', 'max:100', new NoLinksRule],
            'description' => ['nullable', 'string', 'max:280', new NoLinksRule],
            'button_label' => ['required', 'string', 'min:1', 'max:40', new NoLinksRule],
            'success_message' => ['nullable', 'string', 'max:280', new NoLinksRule],
            'asks_for_name' => ['required', 'boolean'],
            'privacy_url' => ['nullable', 'string', 'url:https', 'max:500'],
            'tag_ids' => ['present', 'array', 'max:10'],
            'tag_ids.*' => ['integer', 'min:1'],
            'show_on_organizer_page' => ['required', 'boolean'],
        ];
    }
}
