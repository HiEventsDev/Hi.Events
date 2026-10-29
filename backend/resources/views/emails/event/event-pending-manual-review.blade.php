@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('Your event ":title" has been temporarily unpublished while it undergoes a routine manual review.', ['title' => $event->getTitle()]) }}

{{ __('Our team will review your event shortly. Once the review is complete, your event will be published again automatically. No action is needed from you.') }}

{{ __('If you have any questions, please reply to this email.') }}

{{ __('Thank you') }}

</x-mail::message>
