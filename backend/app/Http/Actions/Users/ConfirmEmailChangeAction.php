<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Exceptions\DecryptionFailedException;
use TicketKitten\Exceptions\EncryptedPayloadExpiredException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Resources\User\UserResource;
use TicketKitten\Service\Handler\User\ConfirmEmailChangeHandler;

class ConfirmEmailChangeAction extends BaseAction
{
    private ConfirmEmailChangeHandler $confirmEmailChangeHandler;

    public function __construct(ConfirmEmailChangeHandler $confirmEmailChangeHandler)
    {
        $this->confirmEmailChangeHandler = $confirmEmailChangeHandler;
    }

    /**
     * @throws DecryptionFailedException|Throwable
     */
    public function __invoke(int $userId, string $token): Response|JsonResponse
    {
        $this->isActionAuthorized($userId, UserDomainObject::class);

        try {
            $user = $this->confirmEmailChangeHandler->handle(
                $token,
                $this->getAuthenticatedUser()->getId()
            );
        } catch (EncryptedPayloadExpiredException) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(UserResource::class, $user);
    }
}
