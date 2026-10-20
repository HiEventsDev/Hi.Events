<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Auth\InvitationResource;
use HiEvents\Services\Domain\Auth\Invitation\InvitationAcceptanceService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetUserInvitationAction extends BaseAction
{
    public function __construct(
        private readonly InvitationAcceptanceService $invitationAcceptanceService,
    ) {}

    public function __invoke(string $inviteToken): JsonResponse
    {
        try {
            $invitation = $this->invitationAcceptanceService->findPending($inviteToken);
        } catch (EncryptedPayloadExpiredException) {
            throw new HttpException(ResponseCodes::HTTP_GONE, __('The invitation has expired'));
        } catch (DecryptionFailedException) {
            throw new HttpException(ResponseCodes::HTTP_BAD_REQUEST, __('The invitation is invalid'));
        } catch (ResourceConflictException $exception) {
            throw new HttpException(ResponseCodes::HTTP_CONFLICT, $exception->getMessage());
        } catch (ResourceNotFoundException) {
            throw new HttpException(
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
                message: __('No user found for this invitation. The invitation may have been revoked.'),
            );
        }

        return $this->resourceResponse(InvitationResource::class, $invitation);
    }
}
