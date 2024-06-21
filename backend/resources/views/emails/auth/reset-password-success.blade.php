<x-mail::message>
{{ __('Hello') }},

{{ __('Your password has been reset for your account on :appName.', ['appName' => config('app.name')]) }}

{{ __('If you did not request a password reset, please immediately contact reset your password.') }}

{{ __('Thank you') }}
</x-mail::message>






