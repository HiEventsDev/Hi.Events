<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Exceptions\DecryptionFailedException;
use TicketKitten\Exceptions\EncryptedPayloadExpiredException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Service\Handler\User\ConfirmEmailAddressHandler;

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
