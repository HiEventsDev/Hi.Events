@php use HiEvents\Helper\Currency @endphp

@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\Services\Application\Handlers\Event\DTO\EventStatsResponseDTO $stats */ @endphp
@php /** @var string $periodLabel */ @endphp
@php /** @var string $currency */ @endphp

<x-mail::message>
# {{ __('Sales Report') }} — {{ $event->getTitle() }}

<br>
{{ __('Period:') }} <b>{{ $periodLabel }}</b>
<br>
<br>

<x-mail::table>
| {{ __('Metric') }} | {{ __('Value') }} |
| :--- | ---: |
| {{ __('Total Orders') }} | {{ number_format($stats->total_orders) }} |
| {{ __('Products Sold') }} | {{ number_format($stats->total_products_sold) }} |
| {{ __('Attendees Registered') }} | {{ number_format($stats->total_attendees_registered) }} |
| {{ __('Gross Sales') }} | {{ Currency::format($stats->total_gross_sales, $currency) }} |
| {{ __('Total Tax') }} | {{ Currency::format($stats->total_tax, $currency) }} |
| {{ __('Total Fees') }} | {{ Currency::format($stats->total_fees, $currency) }} |
| {{ __('Total Refunded') }} | {{ Currency::format($stats->total_refunded, $currency) }} |
| {{ __('Abandoned Orders') }} | {{ number_format($stats->total_orders_abandoned) }} |
| {{ __('Page Views') }} | {{ number_format($stats->total_views) }} |
</x-mail::table>

<br>

@if($stats->daily_stats->isNotEmpty())
## {{ __('Daily Breakdown') }}

<x-mail::table>
| {{ __('Date') }} | {{ __('Orders') }} | {{ __('Sold') }} | {{ __('Gross Sales') }} |
| :--- | ---: | ---: | ---: |
@foreach($stats->daily_stats as $day)
| {{ \Carbon\Carbon::parse($day->date)->format('M j, Y') }} | {{ $day->orders_created }} | {{ $day->products_sold }} | {{ Currency::format($day->total_sales_gross, $currency) }} |
@endforeach
</x-mail::table>
@endif

<br>

<small>{{ __('This is an automated sales report. You can manage report settings in your event dashboard.') }}</small>

</x-mail::message>
