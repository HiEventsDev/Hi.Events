@php use HiEvents\Helper\Currency @endphp

@php /** @uses /backend/app/Mail/OrderSummary.php */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp

<x-mail::message>
# You've received a new order! 🎉

<br>
Congratulations! You've got a new order for <b>{{ $event->getTitle() }}</b>! Please find the details below.
<br>
<br>

Order Amount: <b>{{ Currency::format($order->getTotalGross(), $event->getCurrency()) }}</b><br>
Order ID: <b>{{ $order->getPublicId() }}</b>
<br>

<x-mail::button :url="$orderUrl">
        View Order
</x-mail::button>


<div class="table">
    <table>
        <thead>
        <tr>
            <td><b>Ticket</b></td>
            <td><b>Price</b></td>
            <td><b>Total</b></td>
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
                <b>Total</b>
            </td>
            <td>
                {{ Currency::format($order->getTotalGross(), $event->getCurrency()) }}
            </td>
        </tr>
        </tbody>
    </table>
</div>

Best regards,
<br>
{{config('app.name')}}
</x-mail::message>
