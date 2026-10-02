<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeActivityValidator;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOperatorAuthorizer;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBoxOfficeSession
{
    public const SESSION_HEADER = 'X-Box-Office-Session';

    public const SESSION_ATTRIBUTE = 'box_office_session';

    public const BOX_OFFICE_ATTRIBUTE = 'box_office';

    public function __construct(
        private readonly BoxOfficeSessionService $sessionService,
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly BoxOfficeActivityValidator $activityValidator,
        private readonly BoxOfficeOperatorAuthorizer $operatorAuthorizer,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuthUserService $authUserService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->sessionService->resolve($request->header(self::SESSION_HEADER));
        $boxOffice = $session === null ? null : $this->loadBoxOffice($session->box_office_id);

        if (
            $session === null
            || $boxOffice === null
            || $boxOffice->getEvent() === null
            || $boxOffice->getShortId() !== $request->route('box_office_short_id')
            || ! $this->matchesPinnedDate($session, $boxOffice)
            || ! $this->credentialsStillValid($session, $boxOffice)
        ) {
            return new JsonResponse([
                'message' => __('Your box office session has expired. Please sign in again.'),
                'error_code' => 'BOX_OFFICE_SESSION_EXPIRED',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->activityValidator->assertActive($boxOffice, $boxOffice->getEvent());
        } catch (CannotSellException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
                'error_code' => 'BOX_OFFICE_UNAVAILABLE',
            ], Response::HTTP_CONFLICT);
        }

        $request->attributes->set(self::SESSION_ATTRIBUTE, $session);
        $request->attributes->set(self::BOX_OFFICE_ATTRIBUTE, $boxOffice);

        return $next($request);
    }

    private function loadBoxOffice(int $boxOfficeId): ?BoxOfficeDomainObject
    {
        return $this->boxOfficeRepository
            ->loadRelation(new Relationship(domainObject: EventDomainObject::class, name: 'event'))
            ->findFirstWhere(['id' => $boxOfficeId]);
    }

    private function matchesPinnedDate(BoxOfficeSessionDTO $session, BoxOfficeDomainObject $boxOffice): bool
    {
        return $boxOffice->getEventOccurrenceId() === null
            || $boxOffice->getEventOccurrenceId() === $session->event_occurrence_id;
    }

    private function credentialsStillValid(BoxOfficeSessionDTO $session, BoxOfficeDomainObject $boxOffice): bool
    {
        if ($boxOffice->getPinHash() !== $session->pin_hash) {
            return false;
        }

        if ($session->authenticated_user_id === null) {
            return $boxOffice->getPinHash() !== null;
        }

        if (
            $session->authenticated_account_id === null
            || $this->authUserService->getAuthenticatedUserId() !== $session->authenticated_user_id
        ) {
            return false;
        }

        try {
            $user = $this->userRepository->findByIdAndAccountId($session->authenticated_user_id, $session->authenticated_account_id);
        } catch (ResourceNotFoundException) {
            return false;
        }

        return $this->operatorAuthorizer->canOperateWithoutPin($boxOffice->getEvent(), $user, $session->authenticated_account_id);
    }
}
