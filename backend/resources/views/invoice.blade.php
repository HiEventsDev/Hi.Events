@php use Carbon\Carbon; @endphp
@php use HiEvents\Helper\Currency; @endphp
@php use HiEvents\DomainObjects\Status\InvoiceStatus; @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\InvoiceDomainObject $invoice */ @endphp
@php
    $isPaid = $invoice->getStatus() === InvoiceStatus::PAID->name;
    $isVoid = $invoice->getStatus() === InvoiceStatus::VOID->name;
@endphp

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
            line-height: 1.5;
            color: #1a1a1a;
            padding: 20px 30px;
        }

        table.header-table {
            width: 100%;
            margin-bottom: 25px;
        }

        table.header-table td {
            vertical-align: top;
        }

        .logo-title {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 2px 0;
            letter-spacing: -0.5px;
        }

        .header-event-name {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        .company-details {
            text-align: right;
            line-height: 1.6;
            color: #555;
        }

        .company-name {
            font-weight: bold;
            color: #1a1a1a;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            margin-top: 6px;
            border-radius: 4px;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-paid {
            background-color: #e6f9ee;
            color: #1a7d42;
            border: 1px solid #b8e6cc;
        }

        .status-unpaid {
            background-color: #fff3e0;
            color: #b36b00;
            border: 1px solid #ffe0b2;
        }

        .status-void {
            background-color: #f5f5f5;
            color: #888;
            border: 1px solid #ddd;
        }

        .invoice-info-grid {
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e6e6e6;
            border-radius: 4px;
        }

        .invoice-info-grid td {
            padding: 12px 14px;
            vertical-align: top;
        }

        .info-label {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #888;
            font-size: 10px;
            font-weight: bold;
            display: block;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 13px;
            font-weight: 500;
        }

        .billing-section {
            margin-bottom: 20px;
            background: #f9f9fb;
            padding: 15px;
            border-radius: 4px;
            width: 48%;
            float: left;
        }

        .billing-title {
            color: #888;
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .billing-name {
            font-weight: bold;
            margin-bottom: 2px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            clear: both;
        }

        .items th {
            background: #f9f9fb;
            color: #555;
            text-align: left;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            border-bottom: 2px solid #e6e6e6;
        }

        .items td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .item-description {
            color: #888;
            font-size: 11px;
            margin-top: 4px;
        }

        .item-price-original {
            color: #999;
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
            margin-top: 10px;
        }

        .totals td {
            padding: 6px 10px;
            text-align: right;
        }

        .total-line td {
            border-top: 2px solid #1a1a1a;
            font-weight: bold;
            font-size: 14px;
            padding-top: 12px;
        }

        .amount-paid-line td {
            color: #1a7d42;
            font-size: 12px;
            padding-top: 8px;
        }

        .balance-due-line td {
            border-top: 1px solid #e6e6e6;
            font-weight: bold;
            font-size: 14px;
            padding-top: 10px;
        }

        .subtotal td {
            font-weight: bold;
            padding-top: 12px;
        }

        .breakdown td {
            color: #888;
            font-size: 11px;
        }

        .invoice-notes {
            margin: 30px 0;
            padding: 15px;
            background-color: #f9f9fb;
            border-radius: 4px;
            line-height: 1.6;
            clear: both;
            color: #555;
        }

        .invoice-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e6e6e6;
            text-align: center;
            line-height: 1.6;
            clear: both;
            color: #888;
            font-size: 11px;
        }

        .tax-info {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px dashed #e6e6e6;
            font-size: 11px;
            color: #888;
        }

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

            .status-paid {
                background-color: #e6f9ee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 55%;">
            <h1 class="logo-title">{{ $eventSettings->getInvoiceLabel() ?? __('Invoice') }}</h1>
            <p class="header-event-name">{{ $event->getTitle() }}</p>
        </td>
        <td class="company-details">
            <div class="company-name">{{ $eventSettings->getOrganizationName() }}</div>
            <div>{!! $eventSettings->getOrganizationAddress() !!}</div>
            @if($eventSettings->getSupportEmail())
                <div>{{ $eventSettings->getSupportEmail() }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="invoice-info-grid">
    <tr>
        <td>
            <span class="info-label">{{ __('Invoice Number') }}</span>
            <span class="info-value">#{{ $invoice->getInvoiceNumber() }}</span>
        </td>
        <td>
            <span class="info-label">{{ __('Date Issued') }}</span>
            <span class="info-value">{{ Carbon::parse($invoice->getIssueDate())->format('d/m/Y') }}</span>
        </td>
        @if(!$isPaid && $invoice->getDueDate())
            <td>
                <span class="info-label">{{ __('Due Date') }}</span>
                <span class="info-value">{{ Carbon::parse($invoice->getDueDate())->format('d/m/Y') }}</span>
            </td>
        @endif
        <td>
            @if($isPaid)
                <span class="info-label">{{ __('Amount Paid') }}</span>
            @else
                <span class="info-label">{{ __('Amount Due') }}</span>
            @endif
            <span class="info-value">{{ Currency::format($order->getTotalGross(), $order->getCurrency()) }}</span>
        </td>
        <td>
            <span class="info-label">{{ __('Status') }}</span>
            <span class="info-value">
                @if($isPaid)
                    <span class="status-badge status-paid">{{ __('Paid') }}</span>
                @elseif($isVoid)
                    <span class="status-badge status-void">{{ __('Void') }}</span>
                @else
                    <span class="status-badge status-unpaid">{{ __('Unpaid') }}</span>
                @endif
            </span>
        </td>
    </tr>
</table>

<div class="billing-section">
    <div class="billing-title">{{ __('Billed To') }}</div>
    <div class="billing-name">{{ $order->getFullName() }}</div>
    <div>{{ $order->getEmail() }}</div>
    @if($order->getAddress())
        <div>{{ $order->getBillingAddressString() }}</div>
    @endif
</div>

<table class="items">
    <thead>
    <tr>
        <th class="col-description">{{ __('Description') }}</th>
        <th class="col-rate align-right">{{ __('Rate') }}</th>
        <th class="col-qty align-right">{{ __('Qty') }}</th>
        <th class="col-amount align-right">{{ __('Amount') }}</th>
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
        <td>{{ __('Total') }}</td>
        <td>{{ Currency::format($order->getTotalGross(), $order->getCurrency()) }}</td>
    </tr>

    @if($isPaid)
        <tr class="amount-paid-line">
            <td>{{ __('Amount Paid') }}</td>
            <td>-{{ Currency::format($order->getTotalGross(), $order->getCurrency()) }}</td>
        </tr>
        <tr class="balance-due-line">
            <td>{{ __('Balance Due') }}</td>
            <td>{{ Currency::format(0, $order->getCurrency()) }}</td>
        </tr>
    @endif
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
