<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Http\Request\StartAccountSsoTestRequest;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\AccountSsoConnectionHandler;
use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class StartAccountSsoTestAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoConnectionHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(StartAccountSsoTestRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        if ($this->isImpersonating()) {
            return $this->impersonationForbiddenResponse(__('Single sign-on can\'t be tested while impersonating a user.'));
        }

        try {
            $result = $this->handler->startTest(
                accountId: $accountId,
                userId: $this->getAuthenticatedUser()->getId(),
                tokenId: $this->getAuthTokenId(),
                returnPath: $request->validated('return_path'),
            );
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        } catch (SsoException) {
            throw ValidationException::withMessages(['connection' => __('We couldn\'t reach your identity provider. Check the connection details and try again.')]);
        }

        return $this->jsonResponse([
            'authorization_url' => $result->authorizationUrl,
            'binding' => $result->binding,
        ], wrapInData: true);
    }
}
