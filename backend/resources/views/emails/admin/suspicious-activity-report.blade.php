@php /** @var \HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO[] $findings */ @endphp
@php /** @var string $adminUrl */ @endphp

<x-mail::message>
{{ __('The abuse monitor found activity that needs a look.') }}

@foreach($findings as $finding)
## {{ $finding->summary }}

@foreach($finding->details as $detail)
- {{ $detail }}
@endforeach

@endforeach

<x-mail::button :url="$adminUrl">
{{ __('Open admin') }}
</x-mail::button>
</x-mail::message>
