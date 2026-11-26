@php /** @var string $campaignName */ @endphp
@php /** @var string|null $note */ @endphp
@php /** @var string $actionUrl */ @endphp
@php /** @var string $actionLabel */ @endphp
@php /** @var string $nextStep */ @endphp

@php /** @see \HiEvents\Enterprise\Crm\Mail\CampaignRejectedMail */ @endphp

<x-mail::message>
# {{ __('Your campaign was not approved') }}

<div style="overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">{{ __('We reviewed ":name" and it was not approved to send, so nobody has received it.', ['name' => $campaignName]) }}</div>

@if($note)
<div style="margin: 16px 0; padding: 12px 16px; border-left: 3px solid #ebe7f2; background-color: #f7f5fb; overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;"><strong>{{ __('Note from our team:') }}</strong> {{ $note }}</div>
@endif

<div>{{ $nextStep }}</div>

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
</x-mail::message>
