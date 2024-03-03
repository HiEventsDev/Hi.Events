<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\User\ConfirmEmailAddressHandler;
use HiEvents\Services\Infrastructure\Encyption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encyption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class ConfirmEmailAddressAction extends BaseAction
{
    public function __construct(private ConfirmEmailAddressHandler $confirmEmailAddressHandler)
    {
    }

    /**
     * @throws DecryptionFailedException|Throwable
     */
    public function __invoke(int $userId, string $token): Response|JsonResponse
    {
        $this->isActionAuthorized($userId, UserDomainObject::class);

        try {
            $this->confirmEmailAddressHandler->handle($token);
        } catch (EncryptedPayloadExpiredException) {
            return $this->errorResponse(__('The email confirmation link has expired. Please request a new one.'));
        } catch (DecryptionFailedException) {
            return $this->errorResponse(__('The email confirmation link is invalid.'));
        }

        return $this->noContentResponse();
    }
}
