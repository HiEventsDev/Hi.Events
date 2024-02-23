@php use TicketKitten\Helper\Currency @endphp

@php /** @uses /backend/app/Mail/OrderSummary.php */ @endphp
@php /** @var \TicketKitten\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \TicketKitten\DomainObjects\EventDomainObject $event */ @endphp

<x-mail::message>
# Your Order is Confirmed! 🎉
<br>
    Congratulations! Your order for <b>{{ $event->getTitle() }}</b> was successful! Please find your details below.
<br>

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

<x-mail::button :url="''">
View Order Summary & Tickets
</x-mail::button>

If you have any questions or need assistance, feel free to reach out to our friendly support team
at {{ $supportEmail ?? 'hello@ticketkitten.com' }}.

Best regards,
<br>
TicketKitten.com
</x-mail::message>
