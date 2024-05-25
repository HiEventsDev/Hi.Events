<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\User;

use Illuminate\Validation\Rule;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Request\BaseRequest;

class CreateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
            'role' => Rule::in(Role::valuesArray()),
            'email' => [
                'required',
                'email',
            ],
        ];
    }
}
