<?php

namespace TicketKitten\Http\Request\User;

use Illuminate\Validation\Rule;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DomainObjects\Status\UserStatus;
use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\Rules\RulesHelper;

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
