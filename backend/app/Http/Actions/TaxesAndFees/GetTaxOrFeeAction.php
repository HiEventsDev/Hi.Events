<?php

namespace TicketKitten\Http\Actions\TaxesAndFees;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\AccountDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use TicketKitten\Resources\Tax\TaxAndFeeResource;

class GetTaxOrFeeAction extends BaseAction
{
    private TaxAndFeeRepositoryInterface $taxAndFeeRepository;

    public function __construct(TaxAndFeeRepositoryInterface $taxAndFeeRepository)
    {
        $this->taxAndFeeRepository = $taxAndFeeRepository;
    }

    public function __invoke(int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $tax = $this->taxAndFeeRepository->findWhere([
            'account_id' => $this->getAuthenticatedUser()->getAccountId()
        ]);

        return $this->resourceResponse(TaxAndFeeResource::class, $tax);
    }
}
