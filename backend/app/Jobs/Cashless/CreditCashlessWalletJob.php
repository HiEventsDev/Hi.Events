<?php

namespace HiEvents\Jobs\Cashless;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Application\Handlers\Cashless\CreditCashlessTopupHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CreditCashlessWalletJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly OrderDomainObject $order) {}

    /**
     * @throws Throwable
     */
    public function handle(CreditCashlessTopupHandler $handler): void
    {
        $handler->handle($this->order);
    }
}
