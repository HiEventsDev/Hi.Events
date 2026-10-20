<?php

namespace HiEvents\Http\Actions\TaxesAndFees;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use Illuminate\Http\JsonResponse;

class GetTaxOrFeeAction extends BaseAction
{
    private TaxAndFeeRepositoryInterface $taxAndFeeRepository;

    public function __construct(TaxAndFeeRepositoryInterface $taxAndFeeRepository)
    {
        $this->taxAndFeeRepository = $taxAndFeeRepository;
    }

    public function __invoke(int $accountId): JsonResponse
    {
        $this->isAccountReadAuthorized($accountId);

        $tax = $this->taxAndFeeRepository->findWhere([
            'account_id' => $this->getAuthenticatedAccountId(),
        ]);

        return $this->resourceResponse(TaxAndFeeResource::class, $tax);
    }
}
