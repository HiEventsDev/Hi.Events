<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\User;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Validation\Rule;

class CreateUserRequest extends BaseRequest
{
    use ValidatesAccountUserAccess;

    public function rules(): array
    {
        return [
            'first_name' => PersonNameRule::rules(),
            'last_name' => PersonNameRule::rules(required: false),
            'role' => ['required', Rule::in(Role::getAssignableRoles())],
            'email' => [
                'required',
                'email',
            ],
            ...$this->accessRules(),
        ];
    }
}
