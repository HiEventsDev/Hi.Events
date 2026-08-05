@php /** @var \HiEvents\DomainObjects\AccountDomainObject $account */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $account->getName()]) }},

{{ __('The deletion of your :appName account has been cancelled. Your account is active again.', ['appName' => config('app.name')]) }}

{{ __('Please note that your events were unpublished when the deletion was requested. You will need to republish any events you want to make publicly visible again.') }}

{{ __('Best Regards,') }}<br>
{{ __('The :appName Team', ['appName' => config('app.name')]) }}
</x-mail::message>
