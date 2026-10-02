<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Enterprise\BoxOffice\Exceptions\TooManyPinAttemptsException;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeSessionCreatedDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeActivityValidator;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOperatorAuthorizer;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinAttemptLimiter;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeReaderResolutionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionScopeService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionReaderDTO;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Validation\ValidationException;

class CreateBoxOfficeSessionPublicHandler
{
    public function __construct(
        private readonly BoxOfficeSessionScopeService $scopeService,
        private readonly BoxOfficeActivityValidator $activityValidator,
        private readonly BoxOfficePinService $pinService,
        private readonly BoxOfficeSessionService $sessionService,
        private readonly BoxOfficeReaderResolutionService $readerResolution,
        private readonly BoxOfficeOperatorAuthorizer $operatorAuthorizer,
        private readonly BoxOfficePinAttemptLimiter $pinAttemptLimiter,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws FeatureNotEnabledException
     * @throws CannotSellException
     * @throws UnauthorizedException
     * @throws TooManyPinAttemptsException
     * @throws ValidationException
     */
    public function handle(CreateBoxOfficeSessionDTO $data): BoxOfficeSessionCreatedDTO
    {
        $boxOffice = $this->scopeService->loadBoxOffice($data->box_office_short_id);
        $event = $boxOffice->getEvent();

        $this->featureFlagService->assertEnabled(FeatureFlag::BOX_OFFICE, $event->getAccountId());
        $this->activityValidator->assertActive($boxOffice, $event);

        $authenticatedUserId = $data->pin === null && $this->operatorAuthorizer->canOperateWithoutPin(
            $event,
            $data->authenticated_user,
            $data->authenticated_account_id,
        ) ? $data->authenticated_user->getId() : null;

        if ($authenticatedUserId === null) {
            $this->assertPin($boxOffice, $data->pin, $data->ip_address);
        }

        $scope = $this->scopeService->resolve($boxOffice, $data->event_occurrence_id);
        $reader = $this->readerResolution->resolve($event, $data->stripe_terminal_reader_id);

        $created = $this->sessionService->create(
            boxOffice: $boxOffice,
            operatorName: $data->operator_name,
            eventOccurrenceId: $scope->event_occurrence?->getId(),
            stripeTerminalReaderId: $reader?->id,
            authenticatedUserId: $authenticatedUserId,
            authenticatedAccountId: $authenticatedUserId === null ? null : $data->authenticated_account_id,
        );

        return new BoxOfficeSessionCreatedDTO(
            token: $created->token,
            expires_at: $created->session->expires_at,
            operator_name: $data->operator_name,
            event_occurrence: $scope->event_occurrence,
            reader: $reader === null ? null : new BoxOfficeSessionReaderDTO(id: $reader->id, label: $reader->label),
            check_in_list_short_id: $scope->check_in_list_short_id,
            check_in_available: $scope->check_in_available,
            check_in_unavailable_reason: $scope->check_in_unavailable_reason,
        );
    }

    /**
     * @throws CannotSellException
     * @throws UnauthorizedException
     * @throws TooManyPinAttemptsException
     */
    private function assertPin(BoxOfficeDomainObject $boxOffice, ?string $pin, string $ipAddress): void
    {
        if (! $boxOffice->hasPin()) {
            throw new CannotSellException(__('This box office has no PIN yet. The organizer can set one from the Box Office page.'));
        }

        if ($pin === null) {
            throw new UnauthorizedException(__('Incorrect PIN'));
        }

        $this->pinAttemptLimiter->reserveAttempt($boxOffice, $ipAddress);

        if (! $this->pinService->verify($pin, $boxOffice->getPinHash())) {
            throw new UnauthorizedException(__('Incorrect PIN'));
        }

        $this->pinAttemptLimiter->recordSuccess($boxOffice, $ipAddress);
    }
}
