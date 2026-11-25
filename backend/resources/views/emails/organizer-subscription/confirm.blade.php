<x-mail::message>
# {{ __('Confirm your event subscription') }}

{{ __('Confirm that you want to receive emails when this organizer publishes a new event. This link expires in 24 hours.') }}

<x-mail::button :url="$confirmationUrl">
{{ __('Confirm subscription') }}
</x-mail::button>

{{ __('If you did not request this subscription, you can ignore this email.') }}
</x-mail::message>
