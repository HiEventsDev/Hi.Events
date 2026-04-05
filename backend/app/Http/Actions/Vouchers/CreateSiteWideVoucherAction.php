<?php

namespace HiEvents\Http\Actions\Vouchers;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\PromoCode\CreateUpdatePromoCodeRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\PromoCode\PromoCodeResource;
use HiEvents\Services\Domain\PromoCode\CreateSiteWideVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateSiteWideVoucherAction extends BaseAction
{
    public function __construct(
        private readonly CreateSiteWideVoucherService $service,
    )
    {
    }

    public function __invoke(CreateUpdatePromoCodeRequest $request): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        try {
            $voucher = $this->service->createVoucher(
                accountId: $accountId,
                code: strtolower($request->input('code')),
                discountType: PromoCodeDiscountTypeEnum::fromName($request->input('discount_type')),
                discount: $request->float('discount'),
                expiryDate: $request->input('expiry_date'),
                maxAllowedUsages: $request->input('max_allowed_usages'),
                validFrom: $request->input('valid_from'),
                message: $request->input('message'),
            );
        } catch (ResourceConflictException $e) {
            throw ValidationException::withMessages([
                'code' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: PromoCodeResource::class,
            data: $voucher,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
