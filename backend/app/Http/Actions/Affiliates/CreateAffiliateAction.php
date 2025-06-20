<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Affiliates;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\AffiliateStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Affiliate\CreateUpdateAffiliateRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Affiliate\AffiliateResource;
use HiEvents\Services\Application\Handlers\Affiliate\CreateAffiliateHandler;
use HiEvents\Services\Application\Handlers\Affiliate\DTO\UpsertAffiliateDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateAffiliateAction extends BaseAction
{
    public function __construct(
        private readonly CreateAffiliateHandler $createAffiliateHandler
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateUpdateAffiliateRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $affiliate = $this->createAffiliateHandler->handle(
                $eventId,
                $this->getAuthenticatedAccountId(),
                new UpsertAffiliateDTO(
                    name: $request->input('name'),
                    code: $request->input('code'),
                    email: $request->input('email'),
                    status: AffiliateStatus::from($request->input('status', 'ACTIVE')),
                )
            );
        } catch (ResourceConflictException $e) {
            throw ValidationException::withMessages([
                'code' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: AffiliateResource::class,
            data: $affiliate,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
