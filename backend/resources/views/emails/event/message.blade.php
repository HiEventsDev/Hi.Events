@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\Services\Handlers\Message\DTO\SendMessageDTO $messageData */ @endphp

<x-mail::message>
{!! $messageData->message !!}

<div style="color: #888; margin-top: 30px; font-size: .8em;">
You are receiving this communication because you are registered as an attendee for the following event:
<b>{{ $event->getTitle() }}</b>. If you believe you have received this email in error,
please contact the event organizer at <a
        href="mailto:{{$event->getOrganizer()->getEmail()}}">{{$event->getOrganizer()->getEmail()}}</a>
</div>
</x-mail::message>

