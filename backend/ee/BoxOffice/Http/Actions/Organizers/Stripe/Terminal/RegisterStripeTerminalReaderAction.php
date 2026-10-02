<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Organizers\Stripe\Terminal;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalLocationAddressMissingException;
use HiEvents\Enterprise\BoxOffice\Http\Actions\BaseBoxOfficeAction;
use HiEvents\Enterprise\BoxOffice\Http\Request\Organizer\RegisterStripeTerminalReaderRequest;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal\RegisterStripeTerminalReaderHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

class RegisterStripeTerminalReaderAction extends BaseBoxOfficeAction
{
    public function __construct(
        private readonly RegisterStripeTerminalReaderHandler $handler,
    ) {}

    public function __invoke(RegisterStripeTerminalReaderRequest $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class, Role::ADMIN);
        $this->assertBoxOfficeEnabled();

        try {
            $reader = $this->handler->handle(
                organizerId: $organizerId,
                accountId: $this->getAuthenticatedAccountId(),
                registrationCode: trim($request->validated('registration_code')),
                label: strip_tags(trim($request->validated('label'))),
            );
        } catch (ApiErrorException $exception) {
            throw ValidationException::withMessages(['registration_code' => $exception->getMessage()]);
        } catch (TerminalLocationAddressMissingException|StripeClientConfigurationException|ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse(['id' => $reader->getId(), 'label' => $reader->getLabel()], Response::HTTP_CREATED, true);
    }
}
