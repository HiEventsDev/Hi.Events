@php use Carbon\Carbon; @endphp
@php use HiEvents\Helper\Currency; @endphp
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
            color: #1a1a1a;
            padding: 20px 30px;
        }

        .header {
            margin-bottom: 30px;
            min-height: 100px;
            display: block;
        }

        .logo-title {
            font-size: 36px;
            font-weight: normal;
            color: #1a1a1a;
            margin: 0;
            float: left;
            width: 50%;
        }

        .company-details {
            float: right;
            text-align: right;
            line-height: 1.6;
            width: 45%;
        }

        .company-details > div {
            margin-bottom: 3px;
        }

        .invoice-info-container {
            clear: both;
            padding-top: 20px;
        }

        .invoice-info-grid {
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e6e6e6;
            border-radius: 4px;
        }

        .invoice-info-grid td {
            padding: 10px;
            width: 25%;
            vertical-align: top;
        }

        .info-label {
            color: #8a6bc0;
            font-size: 12px;
            font-weight: bold;
            display: block;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 13px;
        }

        .billing-section {
            margin-bottom: 20px;
            background: #f8f8f8;
            padding: 15px;
            border-radius: 4px;
            width: 48%;
            float: left;
        }

        .billing-title {
            color: #8a6bc0;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            clear: both;
        }

        .items th {
            background: #8a6bc0;
            color: white;
            text-align: left;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .items td {
            padding: 12px 15px;
            border-bottom: 1px solid #e6e6e6;
            vertical-align: middle;
        }

        .item-description {
            color: #666;
            font-size: 11px;
            margin-top: 4px;
        }

        .item-price-original {
            color: #666;
            text-decoration: line-through;
            font-size: 11px;
            margin-bottom: 2px;
        }

        .align-right {
            text-align: right;
        }

        .totals {
            width: 350px;
            margin-left: auto;
            margin-top: 20px;
        }

        .totals td {
            padding: 8px 10px;
            text-align: right;
        }

        .total-line td {
            border-top: 2px solid #8a6bc0;
            font-weight: bold;
            font-size: 14px;
            padding-top: 12px;
            background: #f8f8f8;
        }

        .subtotal td {
            font-weight: bold;
            padding-top: 15px;
        }

        .breakdown td {
            color: #666;
            font-size: 11px;
        }

        .invoice-notes {
            margin: 30px 0;
            padding: 15px;
            background-color: #f8f8f8;
            border-radius: 4px;
            line-height: 1.6;
            clear: both;
        }

        .invoice-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e6e6e6;
            text-align: center;
            line-height: 1.6;
            clear: both;
        }

        .tax-info {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #e6e6e6;
            font-size: 11px;
            color: #666;
        }

        /* Specific column widths for items table */
        .col-description {
            width: 55%;
        }

        .col-rate {
            width: 15%;
        }

        .col-qty {
            width: 15%;
        }

        .col-amount {
            width: 15%;
        }

        @media print {
            body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<div class="header">
    <h1 class="logo-title">{{ $eventSettings->getInvoiceLabel() ?? __('Invoice') }}</h1>
    <div class="company-details">
        <div>{{ $eventSettings->getOrganizationName() }}</div>
        <div>{!! $eventSettings->getOrganizationAddress() !!}</div>
        @if($eventSettings->getSupportEmail())
            <div>{{ $eventSettings->getSupportEmail() }}</div>
        @endif
    </div>
</div>

<div class="invoice-info-container">
    <table class="invoice-info-grid">
        <tr>
            <td>
                <span class="info-label">{{ __('Invoice Number') }}</span>
                <span class="info-value">#{{ $invoice->getInvoiceNumber() }}</span>
            </td>
            <td>
                <span class="info-label">{{ __('Date Issued') }}</span>
                <span class="info-value">{{ Carbon::parse($order->getCreatedAt())->format('d/m/Y') }}</span>
            </td>
            @if($invoice->getDueDate())
                <td>
                    <span class="info-label">{{ __('Due Date') }}</span>
                    <span
                        class="info-value">{{ Carbon::parse($invoice->getDueDate())->format('d/m/Y') }}</span>
                </td>
            @endif
            <td>
                <span class="info-label">{{ __('Amount Due') }}</span>
                <span class="info-value">{{ Currency::format($order->getTotalGross(), $order->getCurrency()) }}</span>
            </td>
        </tr>
    </table>
</div>

<div class="billing-section">
    <div class="billing-title">{{ __('Billed To') }}</div>
    <div>{{ $order->getFullName() }}</div>
    <div>{{ $order->getEmail() }}</div>
    @if($order->getAddress())
        <div>{{ $order->getBillingAddressString() }}</div>
    @endif
</div>

<table class="items">
    <thead>
    <tr>
        <th class="col-description">{{ __('DESCRIPTION') }}</th>
        <th class="col-rate align-right">{{ __('RATE') }}</th>
        <th class="col-qty align-right">{{ __('QTY') }}</th>
        <th class="col-amount align-right">{{ __('AMOUNT') }}</th>
    </tr>
    </thead>
    <tbody>
    @php $totalDiscount = 0; @endphp
    @foreach($invoice->getItems() as $orderItem)
        @php
            $itemDiscount = 0;
            if ($orderItem['price_before_discount']) {
                $itemDiscount = ($orderItem['price_before_discount'] - $orderItem['price']) * $orderItem['quantity'];
                $totalDiscount += $itemDiscount;
            }
        @endphp
        <tr>
            <td>
                {{ $orderItem['item_name'] }}
                @if(!empty($orderItem['description']))
                    <div class="item-description">{{ $orderItem['description'] }}</div>
                @endif
            </td>
            <td class="align-right">
                @if($orderItem['price_before_discount'])
                    <div
                        class="item-price-original">{{ Currency::format($orderItem['price_before_discount'], $order->getCurrency()) }}</div>
                    <div
                        class="item-price-discounted">{{ Currency::format($orderItem['price'], $order->getCurrency()) }}</div>
                @else
                    {{ Currency::format($orderItem['price'], $order->getCurrency()) }}
                @endif
            </td>
            <td class="align-right">{{ $orderItem['quantity'] }}</td>
            <td class="align-right">
                @if($orderItem['price_before_discount'])
                    <div
                        class="item-price-original">{{ Currency::format($orderItem['price_before_discount'] * $orderItem['quantity'], $order->getCurrency()) }}</div>
                    <div
                        class="item-price-discounted">{{ Currency::format($orderItem['total_before_additions'], $order->getCurrency()) }}</div>
                @else
                    {{ Currency::format($orderItem['total_before_additions'], $order->getCurrency()) }}
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr class="subtotal">
        <td>{{ __('Subtotal') }}</td>
        <td>{{ Currency::format($order->getTotalBeforeAdditions(), $order->getCurrency()) }}</td>
    </tr>

    @if($totalDiscount > 0)
        <tr class="breakdown">
            <td>{{ __('Total Discount') }}</td>
            <td>-{{ Currency::format($totalDiscount, $order->getCurrency()) }}</td>
        </tr>
    @endif

    @if($order->getHasTaxes())
        @foreach($order->getTaxesAndFeesRollup()['taxes'] as $tax)
            <tr class="breakdown">
                <td>{{ $tax['name'] }} ({{ $tax['rate'] }}@if($tax['type'] === 'PERCENTAGE')
                        %
                    @else
                        {{ $order->getCurrency() }}
                    @endif)</td>
                <td>{{ Currency::format($tax['value'], $order->getCurrency()) }}</td>
            </tr>
        @endforeach
        <tr class="subtotal">
            <td>{{ __('Total Tax') }}</td>
            <td>{{ Currency::format($order->getTotalTax(), $order->getCurrency()) }}</td>
        </tr>
    @endif

    @if($order->getHasFees())
        @foreach($order->getTaxesAndFeesRollup()['fees'] as $fee)
            <tr class="breakdown">
                <td>{{ $fee['name'] }} ({{ $fee['rate'] }}@if($fee['type'] === 'PERCENTAGE')
                        %
                    @else
                        {{ $order->getCurrency() }}
                    @endif)</td>
                <td>{{ Currency::format($fee['value'], $order->getCurrency()) }}</td>
            </tr>
        @endforeach
        <tr class="subtotal">
            <td>{{ __('Total Service Fee') }}</td>
            <td>{{ Currency::format($order->getTotalFee(), $order->getCurrency()) }}</td>
        </tr>
    @endif

    <tr class="total-line">
        <td>{{ __('Total Amount') }}</td>
        <td>{{ Currency::format($order->getTotalGross(), $order->getCurrency()) }}</td>
    </tr>
</table>

@if($eventSettings->getInvoiceNotes())
    <div class="invoice-notes">
        {!! $eventSettings->getInvoiceNotes() !!}
    </div>
@endif

<div class="invoice-footer">
    @if($eventSettings->getSupportEmail())
        <p>{{ __('For any queries, please contact us at') }} {{ $eventSettings->getSupportEmail() }}</p>
    @endif

    @if((bool) $eventSettings->getInvoiceTaxDetails())
        <div class="tax-info">
            <p><strong>{{ __('Tax Information') }}:</strong> {!! $eventSettings->getInvoiceTaxDetails() !!}</p>
        </div>
    @endif
</div>
</body>
</html>
