<?php

namespace HiEvents\Http\Actions\SelfService\Verification;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\SelfService\SelfServiceSessionResource;
use HiEvents\Services\Application\Handlers\SelfService\Verification\ConfirmSelfServiceVerificationHandler;
use HiEvents\Services\Application\Handlers\SelfService\Verification\DTO\SelfServiceLinkDTO;
use HiEvents\Services\Application\Handlers\SelfService\Verification\RequestSelfServiceVerificationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

abstract class BaseSelfServiceVerificationAction extends BaseAction
{
    public function __construct(
        private readonly RequestSelfServiceVerificationHandler $requestHandler,
        private readonly ConfirmSelfServiceVerificationHandler $confirmHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    protected function sendCode(SelfServiceLinkDTO $link): JsonResponse
    {
        try {
            return $this->jsonResponse(['email' => $this->requestHandler->handle($link)]);
        } catch (SelfServiceVerificationException $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        }
    }

    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    protected function confirmCode(SelfServiceLinkDTO $link): JsonResponse
    {
        try {
            return $this->resourceResponse(
                resource: SelfServiceSessionResource::class,
                data: $this->confirmHandler->handle($link),
            );
        } catch (SelfServiceVerificationException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }
    }
}
