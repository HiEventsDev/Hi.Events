@php use HiEvents\Helper\Currency @endphp

@php /** @uses /backend/app/Mail/OrderSummary.php */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp

<x-mail::message>
# {{ __('You\'ve received a new order!') }} 🎉

<br>
{{ __('Congratulations! You\'ve received a new order for ') }} <b>{{ $event->getTitle() }}</b>! {{ __('Please find the details below.') }}
<br>
<br>

@if($order->isOrderAwaitingOfflinePayment())
<div style="border-radius: 4px; background-color: #f8d7da; color: #842029; margin-bottom: 1.5rem; padding: 1rem;">
<p>
{{ __('ℹ️ This order is pending payment. Please mark the payment as received on the order management page once payment is received.') }}
</p>
</div>
@endif

{{ __('Order Amount:') }} <b>{{ Currency::format($order->getTotalGross(), $event->getCurrency()) }}</b><br>
{{ __('Order ID:') }} <b>{{ $order->getPublicId() }}</b><br>
{{ __('Order Status:') }} <b>{{ $order->getHumanReadableStatus() }}</b>
<br>

<x-mail::button :url="$orderUrl">
    {{ __('View Order') }}
</x-mail::button>

<div class="table">
    <table>
        <thead>
        <tr>
            <td><b>{{ __('Ticket') }}</b></td>
            <td><b>{{ __('Price') }}</b></td>
            <td><b>{{ __('Total') }}</b></td>
        </tr>
        </thead>
        <tbody>
        @foreach ($order->getOrderItems() as $ticket)
            <tr>
                <td>
                    <b>{{ $ticket->getItemName() }} </b> x {{ $ticket->getQuantity()}}
                </td>
                <td>{{ Currency::format($ticket->getPrice() * $ticket->getQuantity(), $event->getCurrency()) }} </td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3">
                <b>{{ __('Total') }}</b>
            </td>
            <td>
                {{ Currency::format($order->getTotalGross(), $event->getCurrency()) }}
            </td>
        </tr>
        </tbody>
    </table>
</div>

{{ __('Best regards') }},
<br>
{{config('app.name')}}
</x-mail::message>






