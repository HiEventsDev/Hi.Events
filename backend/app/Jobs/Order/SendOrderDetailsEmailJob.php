<?php

namespace HiEvents\Jobs\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderDetailsEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly OrderDomainObject $order)
    {
    }

    public function handle(SendOrderDetailsService $service): void
    {
        $service->sendOrderSummaryAndTicketEmails($this->order);
    }
}
