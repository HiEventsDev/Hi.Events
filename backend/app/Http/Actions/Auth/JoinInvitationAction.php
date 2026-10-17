<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\DomainObjects\Enums\AuthMethod;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Auth\JoinInvitationHandler;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

#[AllowedForReadOnlyUsers]
class JoinInvitationAction extends BaseAuthAction
{
    public function __construct(
        private readonly JoinInvitationHandler $handler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(string $inviteToken): JsonResponse
    {
        if ($this->isImpersonating()) {
            return $this->impersonationForbiddenResponse(__('You cannot accept invitations while impersonating a user.'));
        }

        try {
            $loginResponse = $this->handler->handle(
                invitationToken: $inviteToken,
                authenticatedUser: $this->getAuthenticatedUser(),
                context: new AuthContext(method: AuthMethod::tryFrom((string) Auth::payload()->get('auth_method')) ?? AuthMethod::PASSWORD),
            );
        } catch (ResourceConflictException|UnauthorizedException $e) {
            throw new HttpException(ResponseCodes::HTTP_CONFLICT, $e->getMessage());
        } catch (DecryptionFailedException|EncryptedPayloadExpiredException $e) {
            throw new HttpException(ResponseCodes::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (ResourceNotFoundException $e) {
            throw new HttpException(ResponseCodes::HTTP_NOT_FOUND, $e->getMessage());
        }

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts, $loginResponse->user);
    }
}
