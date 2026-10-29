<?php

namespace HiEvents\Http\Request\User;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Validation\Rules\Password;

class UpdateMeRequest extends BaseRequest
{
    /**
     * @todo This endpoint is doing too much. It should be split into two endpoints, one for updating the user's
     *      profile and one for updating the user's password.
     */
    public function rules(): array
    {
        return [
            'first_name' => $this->nameRules($this->user()?->first_name),
            'last_name' => $this->nameRules($this->user()?->last_name),
            'email' => 'required_without_all:current_password,password,password_confirmation,notify_new_orders|email',
            'timezone' => 'required_without_all:current_password,password,password_confirmation,notify_new_orders|timezone',
            'locale' => 'required_without_all:current_password,password,password_confirmation,notify_new_orders|in:'.implode(',', Locale::getSupportedLocales()),

            'current_password' => [
                'required_with:password,password_confirmation',
                'min:8',
            ],
            'password' => [
                'required_with:current_password',
                'confirmed',
                Password::min(8),
            ],
            'confirmation_password' => ['nullable', 'string', 'max:255'],
            'reauth_proof' => ['nullable', 'string', 'max:128'],
            'marketing_opt_in' => 'boolean|nullable',
            'notify_new_orders' => 'boolean|nullable',
        ];
    }

    private function nameRules(?string $currentValue): array
    {
        return [
            'required_without_all:current_password,password,password_confirmation,notify_new_orders',
            'string',
            'min:1',
            'max:'.PersonNameRule::maxLengthFor($currentValue),
            new PersonNameRule($currentValue),
        ];
    }
}
