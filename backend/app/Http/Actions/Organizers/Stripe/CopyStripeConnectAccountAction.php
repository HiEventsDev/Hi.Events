<?php

namespace HiEvents\Http\Actions\Organizers\Stripe;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\Stripe\OrganizerStripeConnectAccountResponseResource;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\CopyStripeConnectAccountHandler;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CopyStripeConnectAccountDTO;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CopyStripeConnectAccountAction extends BaseAction
{
    public function __construct(
        private readonly CopyStripeConnectAccountHandler $copyStripeConnectAccountHandler,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(int $organizerId, int $sourceOrganizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class, Role::ADMIN);
        $this->isActionAuthorized($sourceOrganizerId, OrganizerDomainObject::class, Role::ADMIN);

        try {
            $result = $this->copyStripeConnectAccountHandler->handle(CopyStripeConnectAccountDTO::from([
                'targetOrganizerId' => $organizerId,
                'sourceOrganizerId' => $sourceOrganizerId,
                'accountId' => $this->getAuthenticatedAccountId(),
            ]));
        } catch (SaasModeEnabledException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_NOT_FOUND,
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        return $this->resourceResponse(
            resource: OrganizerStripeConnectAccountResponseResource::class,
            data: $result,
        );
    }
}
