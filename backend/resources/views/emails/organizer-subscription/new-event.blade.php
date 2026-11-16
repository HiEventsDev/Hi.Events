<x-mail::message>
# {{ $event->getTitle() }}

{{ __('New event from :organizer', ['organizer' => $event->getOrganizer()->getName()]) }}

@foreach($dates as $date)
{{ $date }}<br>
@endforeach

<x-mail::button :url="$eventUrl">
{{ __('View Event') }}
</x-mail::button>

<a href="{{ $unsubscribeUrl }}">{{ __('Unsubscribe from this organizer') }}</a>
</x-mail::message>
