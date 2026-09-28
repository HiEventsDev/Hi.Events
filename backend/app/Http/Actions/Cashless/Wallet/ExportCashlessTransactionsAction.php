<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exports\CashlessTransactionsExport;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\GetCashlessTransactionsHandler;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportCashlessTransactionsAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessTransactionsHandler $getCashlessTransactionsHandler,
        private readonly CashlessTransactionsExport $export,
    ) {}

    public function __invoke(int $eventId): BinaryFileResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $transactions = $this->getCashlessTransactionsHandler->handle($eventId, new QueryParamsDTO(
            page: 1,
            per_page: 10000,
        ));

        return Excel::download(
            $this->export->withData($transactions),
            'cashless-transactions.xlsx'
        );
    }
}
