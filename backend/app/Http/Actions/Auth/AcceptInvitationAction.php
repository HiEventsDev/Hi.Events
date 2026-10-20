<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\InvitationSignInRequiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Auth\AcceptInvitationRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Auth\AcceptInvitationHandler;
use HiEvents\Services\Application\Handlers\Auth\DTO\AcceptInvitationDTO;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class AcceptInvitationAction extends BaseAction
{
    public const SIGN_IN_REQUIRED_ERROR_CODE = 'INVITATION_SIGN_IN_REQUIRED';

    public function __construct(private readonly AcceptInvitationHandler $handler) {}

    public function __invoke(AcceptInvitationRequest $request, string $inviteToken): Response|JsonResponse
    {
        try {
            $this->handler->handle(AcceptInvitationDTO::fromArray($request->validated() + ['invitation_token' => $inviteToken]));
        } catch (InvitationSignInRequiredException $e) {
            return $this->jsonResponse([
                'message' => $e->getMessage(),
                'error_code' => self::SIGN_IN_REQUIRED_ERROR_CODE,
            ], ResponseCodes::HTTP_CONFLICT);
        } catch (ResourceConflictException $e) {
            throw new HttpException(ResponseCodes::HTTP_CONFLICT, $e->getMessage());
        } catch (DecryptionFailedException|EncryptedPayloadExpiredException $e) {
            throw new HttpException(ResponseCodes::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (ResourceNotFoundException $e) {
            throw new HttpException(ResponseCodes::HTTP_NOT_FOUND, $e->getMessage());
        }

        return $this->noContentResponse();
    }
}
