<?php

declare(strict_types=1);

namespace TicketKitten\Http\Request\User;

use Illuminate\Validation\Rule;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\Http\Request\BaseRequest;

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
