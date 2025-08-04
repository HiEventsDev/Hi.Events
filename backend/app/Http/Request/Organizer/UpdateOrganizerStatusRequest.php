<?php

namespace HiEvents\Http\Request\Organizer;

use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizerStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(OrganizerStatus::valuesArray())],
        ];
    }
}