@php /** @see \HiEvents\Enterprise\Crm\Mail\CampaignPausedMail */ @endphp

<x-mail::message>
# {{ __('An audience campaign was paused') }}

<ul style="overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">
<li><strong>{{ __('Campaign') }}:</strong> {{ $campaignName }} (#{{ $campaignId }})</li>
<li><strong>{{ __('Account') }}:</strong> {{ $accountName }} (#{{ $accountId }})</li>
<li><strong>{{ __('Reason:') }}</strong> {{ $reason }}</li>
</ul>

{{ __('Its remaining recipients will not receive it, and the organizer has been told.') }}
</x-mail::message>
