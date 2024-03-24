<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Handlers\User\ConfirmEmailChangeHandler;
use HiEvents\Services\Handlers\User\DTO\ConfirmEmailChangeDTO;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Throwable;

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
            $user = $this->confirmEmailChangeHandler->handle(new ConfirmEmailChangeDTO(
                token: $token,
                accountId: $this->getAuthenticatedAccountId(),
            ));
        } catch (EncryptedPayloadExpiredException) {
            return $this->notFoundResponse();
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), HttpCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(UserResource::class, $user);
    }
}
