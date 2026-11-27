<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\PersonNameRule;

class UpsertContactRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'email', 'max:254'],
            'first_name' => PersonNameRule::rules(required: false),
            'last_name' => PersonNameRule::rules(required: false),
            'restore_deleted' => [$this->isMethod('POST') ? 'sometimes' : 'prohibited', 'boolean'],
        ];
    }
}
