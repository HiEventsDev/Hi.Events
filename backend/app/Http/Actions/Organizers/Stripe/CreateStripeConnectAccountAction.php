<?php

namespace HiEvents\Http\Actions\Organizers\Stripe;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\CreateStripeConnectAccountFailedException;
use HiEvents\Exceptions\CreateStripeConnectAccountLinksFailedException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Organizer\Stripe\OrganizerStripeConnectAccountResponseResource;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\CreateStripeConnectAccountHandler;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CreateStripeConnectAccountDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[RequiresPermission(Permission::MANAGE_PAYMENTS)]
class CreateStripeConnectAccountAction extends BaseAction
{
    public function __construct(
        private readonly CreateStripeConnectAccountHandler $createStripeConnectAccountHandler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        try {
            $result = $this->createStripeConnectAccountHandler->handle(CreateStripeConnectAccountDTO::from([
                'organizerId' => $organizerId,
                'accountId' => $this->getAuthenticatedAccountId(),
                'platform' => $request->has('platform')
                    ? StripePlatform::from($request->get('platform'))
                    : null,
            ]));
        } catch (CreateStripeConnectAccountLinksFailedException|CreateStripeConnectAccountFailedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
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
        }

        return $this->resourceResponse(
            resource: OrganizerStripeConnectAccountResponseResource::class,
            data: $result,
        );
    }
}
