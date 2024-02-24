<?php

namespace HiEvents\Http\Actions\TaxesAndFees;

use Illuminate\Http\Response;
use Throwable;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\DeleteTaxDTO;
use HiEvents\Service\Handler\TaxAndFee\DeleteTaxHandler;

class DeleteTaxOrFeeAction extends BaseAction
{
    private DeleteTaxHandler $deleteTaxHandler;

    public function __construct(DeleteTaxHandler $deleteTaxHandler)
    {
        $this->deleteTaxHandler = $deleteTaxHandler;
    }

    /**
     * @throws Throwable
     * @throws ResourceConflictException
     */
    public function __invoke(int $accountId, int $taxOrFeeId): Response
    {
        $this->isActionAuthorized($taxOrFeeId, TaxAndFeesDomainObject::class);

        $this->deleteTaxHandler->handle(new DeleteTaxDTO(
            taxId: $taxOrFeeId,
            accountId: $this->getAuthenticatedUser()->getAccountId(),
        ));

        return $this->deletedResponse();
    }
}
