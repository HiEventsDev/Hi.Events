<?php

namespace TicketKitten\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\Service\Common\Mail\SendOrderDetailsService;

class SendOrderDetailsEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly OrderDomainObject $order)
    {
    }

    public function handle(SendOrderDetailsService $service): void
    {
        $service->sendOrderSummaryAndTicketEmails($this->order);
    }
}
