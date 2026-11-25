@php use HiEvents\Helper\StringHelper; @endphp
@php /** @var \HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO[] $findings */ @endphp
@php /** @var string $adminUrl */ @endphp

<x-mail::message>
{{ __('The abuse monitor found activity that needs a look.') }}

@foreach($findings as $finding)
## {{ $finding->summary }}

@foreach($finding->items as $item)
- **{{ StringHelper::escapeMarkdown($item->label) }}**@foreach($item->facts as $fact) · {{ StringHelper::escapeMarkdown($fact) }}@endforeach<br>
@if($item->account)
{{ __('Account :id', ['id' => $item->account->id]) }}: {{ StringHelper::escapeMarkdown($item->account->name) }}@if($item->account->email) · {{ StringHelper::escapeMarkdown($item->account->email) }}@endif · {{ __('signed up :time', ['time' => $item->account->createdAt->diffForHumans()]) }} · {{ $item->account->isVerified ? __('verified') : __('unverified') }} · {{ $item->account->hasStripe ? __('Stripe connected') : __('no Stripe') }} · {{ __('live events: :count', ['count' => $item->account->liveEvents]) }}@if($item->account->isSuspended) · **{{ __('already suspended') }}**@endif<br>
[{{ __('Open account') }}]({{ $adminUrl }}/accounts/{{ $item->account->id }})@unless($item->account->isSuspended) · [{{ __('Suspend account') }}]({{ $adminUrl }}/accounts/{{ $item->account->id }}?action=suspend)@endunless
@elseif($item->accountId)
[{{ __('Open account') }}]({{ $adminUrl }}/accounts/{{ $item->accountId }})
@elseif($item->accountSearch)
[{{ __('Find matching accounts') }}]({{ $adminUrl }}/accounts?search={{ rawurlencode($item->accountSearch) }})
@endif
@endforeach


**{{ __('Next step') }}:** {{ $finding->nextStep }}
@if($finding->adminPath)
[{{ $finding->adminLinkLabel }}]({{ $adminUrl }}/{{ $finding->adminPath }})
@endif

@endforeach

<x-mail::button :url="$adminUrl">
{{ __('Open admin') }}
</x-mail::button>
</x-mail::message>
