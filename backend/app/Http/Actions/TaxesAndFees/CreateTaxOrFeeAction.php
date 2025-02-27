<?php

namespace HiEvents\Http\Actions\TaxesAndFees;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Exceptions\ResourceNameAlreadyExistsException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\TaxOrFee\CreateTaxOrFeeRequest;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use HiEvents\Services\Application\Handlers\TaxAndFee\CreateTaxOrFeeHandler;
use HiEvents\Services\Application\Handlers\TaxAndFee\DTO\UpsertTaxDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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
                'account_id' => $this->getAuthenticatedAccountId(),
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
