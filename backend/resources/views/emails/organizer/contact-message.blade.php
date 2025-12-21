@php /** @var string $organizerName */ @endphp
@php /** @var string $senderName */ @endphp
@php /** @var string $senderEmail */ @endphp
@php /** @var string $messageContent */ @endphp
@php /** @var string $replySubject */ @endphp

@php /** @see \HiEvents\Mail\Organizer\OrganizerContactEmail */ @endphp

<x-mail::message>
{{ __('Hello :name', ['name' => $organizerName]) }},

{{ __('You have received a new message from') }} **{{ $senderName }}** ({{ $senderEmail }}).

<div style="border-radius: 5px; background-color: #eeeeee; margin: 10px 0; padding: 20px;">

{!! nl2br(e($messageContent)) !!}

</div>

<x-mail::button :url="'mailto:' . $senderEmail . '?subject=' . $replySubject">
{{ __('Reply to :name', ['name' => $senderName]) }}
</x-mail::button>

{{ __('This message was sent via your organizer contact form.') }}

</x-mail::message>
