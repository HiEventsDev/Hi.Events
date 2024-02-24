<?php

namespace HiEvents\Http\Request\User;

use Illuminate\Validation\Rules\Password;
use HiEvents\Http\Request\BaseRequest;

class UpdateMeRequest extends BaseRequest
{
    /**
     * @todo This endpoint is doing too much. It should be split into two endpoints, one for updating the user's
     *      profile and one for updating the user's password.
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required_without_all:current_password,password,password_confirmation|min:2',
            'last_name' => 'required_without_all:current_password,password,password_confirmation|min:2',
            'email' => 'required_without_all:current_password,password,password_confirmation|email',
            'timezone' => 'required_without_all:current_password,password,password_confirmation|timezone',

            'current_password' => [
                'required_with:password,password_confirmation',
                'min:8',
            ],
            'password' => [
                'required_with:current_password',
                'confirmed',
                Password::min(8)
            ],
        ];
    }
}
