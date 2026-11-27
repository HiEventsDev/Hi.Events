@php /** @var string|null $organizerName */ @endphp
@php /** @var string $confirmUrl */ @endphp
@php /** @var int $expiresInDays */ @endphp

@php /** @see \HiEvents\Enterprise\Crm\Mail\SubscriptionConfirmationMail */ @endphp

<x-mail::message>
# {{ __('Confirm your subscription') }}

@if($organizerName)
<div style="overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">{{ __('You asked to get updates from :organizer. Confirm below to start receiving them.', ['organizer' => $organizerName]) }}</div>
@else
<div>{{ __('You asked to get updates from an event organizer on :appName. Confirm below to start receiving them.', ['appName' => config('app.name')]) }}</div>
@endif

<x-mail::button :url="$confirmUrl">
{{ __('Confirm subscription') }}
</x-mail::button>

<div>{{ trans_choice('{1} This link expires in :count day. If this wasn\'t you, ignore this email and nothing will happen.|[2,*] This link expires in :count days. If this wasn\'t you, ignore this email and nothing will happen.', $expiresInDays) }}</div>
</x-mail::message>
