@php use Carbon\Carbon; @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\InvoiceDomainObject $invoice */ @endphp

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $eventSettings->getInvoiceLabel() ?? __('Invoice') }} #{{ $invoice->getInvoiceNumber() }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #2c3e50;
            background: #ffffff;
            padding: 20px;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        h2 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #34495e;
        }

        h3 {
            font-size: 14px;
            margin: 15px 0 10px 0;
            color: #34495e;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        p {
            margin-bottom: 5px;
        }

        .invoice-header {
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-details {
            float: left;
            width: 60%;
        }

        .invoice-details {
            float: right;
            width: 40%;
            text-align: right;
        }

        .billing-details {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th {
            background-color: #3498db;
            color: #ffffff;
            padding: 10px;
            text-align: left;
            font-weight: normal;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
        }

        .order-items table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .totals table {
            width: 350px;
            margin-left: auto;
            margin-top: 20px;
        }

        .totals td {
            padding: 8px;
            text-align: right;
        }

        .totals .subtotal {
            border-bottom: 1px solid #ecf0f1;
        }

        .totals .breakdown {
            font-size: 11px;
            color: #505050;
        }

        .totals .breakdown td {
            padding: 4px 8px;
        }

        .totals .subtotal-line {
            border-bottom: 1px solid #ecf0f1;
            font-weight: bold;
        }

        .totals tr.total {
            background-color: #3498db;
            color: #ffffff;
            font-weight: bold;
        }

        .totals tr.total td {
            font-size: 14px;
            padding: 10px 8px;
        }

        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            color: #7f8c8d;
        }

        .tax-info {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #ecf0f1;
            font-size: 11px;
        }

        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .invoice-header {
                padding-top: 20px;
            }
        }
    </style>
</head>
<body>
<div class="invoice-header clearfix">
    <div class="company-details">
        <h1>{{ $eventSettings->getOrganizationName() }}</h1>
        <p>{!! $eventSettings->getOrganizationAddress() !!}</p>
    </div>
    <div class="invoice-details">
        <h2>{{ $eventSettings->getInvoiceLabel() ?? __('Invoice') }}</h2>
        <p><strong>{{ __('Invoice No') }}:</strong> #{{ $invoice->getInvoiceNumber() }}</p>
        <p><strong>{{ __('Date') }}:</strong> {{ Carbon::parse($order->getCreatedAt())->format('d/m/Y') }}</p>
    </div>
</div>

<div class="billing-details">
    <h3>{{ __('Bill To') }}</h3>
    <p><strong>{{ $order->getFullName() }}</strong></p>
    <p>{{ $order->getEmail() }}</p>
    @if($order->getAddress())
        <p>{{ $order->getBillingAddressString() }}</p>
    @endif
</div>

<div class="order-items">
    <h3>{{ __('Order Summary') }}</h3>
    <table>
        <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th style="width: 45%">{{ __('Description') }}</th>
            <th style="width: 15%">{{ __('Quantity') }}</th>
            <th style="width: 15%">{{ __('Unit Price') }}</th>
            {{--            @if($order->getHasTaxes())--}}
            {{--                <th style="width: 15%">{{ __('Tax') }}</th>--}}
            {{--            @endif--}}
            {{--            @if($order->getHasFees())--}}
            {{--                <th style="width: 15%">{{ __('Service Fee') }}</th>--}}
            {{--            @endif--}}
            <th style="width: 20%">{{ __('Amount') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($invoice->getItems() as $index => $orderItem)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $orderItem['item_name'] }}</td>
                <td>{{ $orderItem['quantity'] }}</td>
                <td>{{ number_format($orderItem['price'], 2) }} {{ $order->getCurrency() }}</td>
                {{--                @if($order->getHasTaxes())--}}
                {{--                    <td>{{ number_format($orderItem['total_tax'], 2) }} {{ $order->getCurrency() }}</td>--}}
                {{--                @endif--}}
                {{--                @if($order->getHasFees())--}}
                {{--                    <td>{{ number_format($orderItem['total_service_fee'], 2) }} {{ $order->getCurrency() }}</td>--}}
                {{--                @endif--}}
                <td>{{ number_format($orderItem['total_before_additions'], 2) }} {{ $order->getCurrency() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="totals">
    <table>
        <tr class="subtotal">
            <td>{{ __('Subtotal') }}:</td>
            <td>{{ number_format($order->getTotalBeforeAdditions(), 2) }} {{ $order->getCurrency() }}</td>
        </tr>

        @if($order->getHasTaxes())
            <!-- Tax Breakdown -->
            @foreach($order->getTaxesAndFeesRollup()['taxes'] as $tax)
                <tr class="breakdown">
                    <td>{{ $tax['name'] }} ({{ $tax['rate'] }} @if($tax['type'] === 'PERCENTAGE') % @else {{ $order->getCurrency() }} @endif:</td>
                    <td>{{ number_format($tax['value'], 2) }} {{ $order->getCurrency() }}</td>
                </tr>
            @endforeach

            <tr class="subtotal-line">
                <td>{{ __('Total Tax') }}:</td>
                <td>{{ number_format($order->getTotalTax(), 2) }} {{ $order->getCurrency() }}</td>
            </tr>
        @endif

        @if($order->getHasFees())
            <!-- Fee Breakdown -->
            @foreach($order->getTaxesAndFeesRollup()['fees'] as $fee)
                <tr class="breakdown">
                    <td>{{ $fee['name'] }} ({{ $fee['rate'] }} @if($tax['type'] === 'PERCENTAGE') % @else {{ $order->getCurrency() }} @endif )</td>
                    <td>{{ number_format($fee['value'], 2) }} {{ $order->getCurrency() }}</td>
                </tr>
            @endforeach

            <tr class="subtotal-line">
                <td>{{ __('Total Service Fee') }}:</td>
                <td>{{ number_format($order->getTotalFee(), 2) }} {{ $order->getCurrency() }}</td>
            </tr>
        @endif

        <tr class="total">
            <td>{{ __('Total Amount') }}:</td>
            <td>{{ number_format($order->getTotalGross(), 2) }} {{ $order->getCurrency() }}</td>
        </tr>
    </table>
</div>

<div class="invoice-footer">
    <p>{{ __('Thank you for your business!') }}</p>
    <p><strong>{{ $eventSettings->getOrganizationName() }}</strong></p>

    @if($eventSettings->getSupportEmail())
        <p>{{ __('For any queries, please contact us at') }} {{ $eventSettings->getSupportEmail() }}</p>
    @endif

    @if($eventSettings->getTaxDetails())
        <div class="tax-info">
            <p><strong>{{ __('Tax Information') }}:</strong> {!! $eventSettings->getTaxDetails() !!}</p>
        </div>
    @endif
</div>
</body>
</html>
