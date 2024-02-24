<?php

namespace HiEvents\Http\Actions\TaxesAndFees;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Exceptions\ResourceNameAlreadyExistsException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\UpsertTaxDTO;
use HiEvents\Http\Request\TaxOrFee\CreateTaxOrFeeRequest;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use HiEvents\Service\Handler\TaxAndFee\CreateTaxOrFeeHandler;

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
