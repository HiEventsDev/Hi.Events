@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO $messageData */ @endphp

@php /** @see \HiEvents\Mail\Event\EventMessage */ @endphp

@php $organizerName = $event->getOrganizer()?->getName() ?: __('the event organizer') @endphp

<x-mail::message>
<div style="background-color: #f7f5fb; border: 1px solid #ebe7f2; border-radius: 10px; padding: 10px 16px; margin: 0 0 24px 0; color: #56506a; font-size: 13px; line-height: 1.6; text-align: center;">
{{ __('This message is from :organizer, not from :platform.', ['organizer' => $organizerName, 'platform' => config('app.name')]) }}
</div>

{!! $messageData->message !!}

{!! $eventSettings->getGetEmailFooterHtml() !!}

<div style="color: #888; margin-top: 30px; font-size: .8em;">
{{ __('You are receiving this communication because you are registered as an attendee for the following event:') }}
<b>{{ $event->getTitle() }}</b>. {{ __('If you believe you have received this email in error,') }}
{{ __('please contact the event organizer at') }} <a
        href="mailto:{{$eventSettings->getSupportEmail()}}">{{$eventSettings->getSupportEmail()}}</a>.
{{ __('If you believe this is spam, please report it to') }} <a href="mailto:{{config('mail.from.address')}}">{{config('mail.from.address')}}</a>.
</div>
</x-mail::message>
