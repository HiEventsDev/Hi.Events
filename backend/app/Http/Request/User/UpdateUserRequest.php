<?php

namespace HiEvents\Http\Request\User;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => RulesHelper::STRING,
            'last_name' => RulesHelper::STRING,
            'status' => Rule::in([UserStatus::INACTIVE->name, UserStatus::ACTIVE->name]), // don't allow INVITED
            'role' => Rule::in(Role::valuesArray())
        ];
    }
}
