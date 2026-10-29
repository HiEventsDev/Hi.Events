@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var string $amount */ @endphp
@php /** @var string $orderUrl */ @endphp

@php /** @see \HiEvents\Mail\Organizer\OfflinePaymentReportedMail */ @endphp

<x-mail::message>
# {{ __('A buyer says they have paid') }}

{{ __('The buyer of an order for :event says they have sent their offline payment. Check that the money has arrived, then mark the order as paid. Their tickets stay marked as unpaid until you do.', ['event' => $event->getTitle()]) }}

{{ __('Order reference') }}: <b>{{ $order->getPublicId() }}</b><br>
{{ __('Amount') }}: <b>{{ $amount }}</b><br>
{{ __('Name') }}: <b>{{ $order->getFullName() }}</b><br>
{{ __('Email') }}: <b>{{ $order->getEmail() }}</b><br>
@if($order->getOfflinePaymentNote())
{{ __('Note from the buyer') }}: <b>{{ $order->getOfflinePaymentNote() }}</b>
@endif

<x-mail::button :url="$orderUrl">
{{ __('Review order') }}
</x-mail::button>

</x-mail::message>
