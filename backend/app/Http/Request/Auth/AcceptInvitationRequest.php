<?php

namespace TicketKitten\Http\Request\Auth;

use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\Rules\RulesHelper;

class AcceptInvitationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => RulesHelper::REQUIRED_STRING,
            'last_name' => RulesHelper::REQUIRED_STRING,
            'password' => 'required|string|min:8|confirmed',
            'timezone' => ['required', 'timezone:all'],
        ];
    }
}
