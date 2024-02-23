<?php

namespace TicketKitten\Http\Actions\TaxesAndFees;

use Illuminate\Http\Response;
use Throwable;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\Exceptions\ResourceConflictException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\DeleteTaxDTO;
use TicketKitten\Service\Handler\TaxAndFee\DeleteTaxHandler;

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
