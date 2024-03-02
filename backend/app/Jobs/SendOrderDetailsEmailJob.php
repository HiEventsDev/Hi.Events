<?php

namespace HiEvents\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Common\Mail\SendOrderDetailsService;

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
