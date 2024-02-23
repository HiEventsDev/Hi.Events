<?php

namespace TicketKitten\Service\Handler\Account;

use Psr\Log\LoggerInterface;
use TicketKitten\DomainObjects\AccountDomainObject;
use TicketKitten\Http\DataTransferObjects\UpdateAccountDTO;
use TicketKitten\Repository\Interfaces\AccountRepositoryInterface;

class UpdateAccountHanlder
{
    private AccountRepositoryInterface $accountRepository;

    private LoggerInterface $logger;

    public function __construct(AccountRepositoryInterface $accountRepository, LoggerInterface $logger)
    {
        $this->accountRepository = $accountRepository;
        $this->logger = $logger;
    }

    public function handle(UpdateAccountDTO $data): AccountDomainObject
    {
        $this->accountRepository->updateWhere(
            attributes: [
                'name' => $data->name,
                'email' => $data->email,
                'currency_code' => $data->currency_code,
                'timezone' => $data->timezone,
            ],
            where: [
                'id' => $data->account_id,
            ],
        );

        $this->logger->info('Account Updated', [
            'id' => $data->account_id,
            'updated_by' => $data->updated_by_user_id,
        ]);

        return $this->accountRepository->findById($data->account_id);
    }
}
