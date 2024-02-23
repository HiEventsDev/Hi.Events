<?php

namespace TicketKitten\Http\Actions\TaxesAndFees;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use TicketKitten\DomainObjects\AccountDomainObject;
use TicketKitten\Exceptions\ResourceNameAlreadyExistsException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpsertTaxDTO;
use TicketKitten\Http\Request\TaxOrFee\CreateTaxOrFeeRequest;
use TicketKitten\Resources\Tax\TaxAndFeeResource;
use TicketKitten\Service\Handler\TaxAndFee\CreateTaxOrFeeHandler;

class CreateTaxOrFeeAction extends BaseAction
{
    private CreateTaxOrFeeHandler $taxHandler;

    public function __construct(CreateTaxOrFeeHandler $taxHandler)
    {
        $this->taxHandler = $taxHandler;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateTaxOrFeeRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        try {
            $payload = array_merge($request->validated(), [
                'account_id' => $this->getAuthenticatedUser()->getAccountId(),
            ]);

            $tax = $this->taxHandler->handle(UpsertTaxDTO::fromArray($payload));
        } catch (ResourceNameAlreadyExistsException $e) {
            throw ValidationException::withMessages([
                'name' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(TaxAndFeeResource::class, $tax);
    }
}
