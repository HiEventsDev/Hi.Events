<?php

namespace HiEvents\Http\Actions\Auth;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use HiEvents\Exceptions\DecryptionFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\AcceptInvitationDTO;
use HiEvents\Http\Request\Auth\AcceptInvitationRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Service\Handler\Auth\AcceptInvitationHandler;

class AcceptInvitationAction extends BaseAction
{
    public function __construct(private readonly AcceptInvitationHandler $handler)
    {
    }

    public function __invoke(AcceptInvitationRequest $request, string $inviteToken): Response
    {
        try {
            $this->handler->handle(AcceptInvitationDTO::fromArray($request->validated() + ['invitation_token' => $inviteToken]));
        } catch (ResourceConflictException $e) {
            throw new HttpException(ResponseCodes::HTTP_CONFLICT, $e->getMessage());
        } catch (DecryptionFailedException $e) {
            throw new HttpException(ResponseCodes::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (ResourceNotFoundException $e) {
            throw new HttpException(ResponseCodes::HTTP_NOT_FOUND, $e->getMessage());
        }

        return $this->noContentResponse();
    }
}
