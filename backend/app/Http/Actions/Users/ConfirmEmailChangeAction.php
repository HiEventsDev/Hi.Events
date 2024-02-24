<?php

namespace HiEvents\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\DecryptionFailedException;
use HiEvents\Exceptions\EncryptedPayloadExpiredException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\User\UserResource;
use HiEvents\Service\Handler\User\ConfirmEmailChangeHandler;

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
