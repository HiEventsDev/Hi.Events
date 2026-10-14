@php /** @var string $renderedBody */ @endphp
@php /** @var string $ctaLabel */ @endphp
@php /** @var string $ctaUrl */ @endphp
@php /** @var string $unsubscribeUrl */ @endphp

<x-mail::message>
{!! $renderedBody !!}

<x-mail::button :url="$ctaUrl">
    {{ $ctaLabel }}
</x-mail::button>

<x-slot:subcopy>
{{ __('You are receiving this because you have a :app_name organizer account.', ['app_name' => config('app.name')]) }} [{{ __('Unsubscribe') }}]({{ $unsubscribeUrl }})
</x-slot:subcopy>
</x-mail::message>
