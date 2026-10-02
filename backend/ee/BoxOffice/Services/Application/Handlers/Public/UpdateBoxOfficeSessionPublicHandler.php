<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeSessionCreatedDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\UpdateBoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeReaderResolutionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionScopeService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionReaderDTO;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Validation\ValidationException;

class UpdateBoxOfficeSessionPublicHandler
{
    public function __construct(
        private readonly BoxOfficeSessionScopeService $scopeService,
        private readonly BoxOfficeSessionService $sessionService,
        private readonly BoxOfficeReaderResolutionService $readerResolution,
        private readonly StripeTerminalReaderRepositoryInterface $readerRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    public function handle(UpdateBoxOfficeSessionDTO $data): BoxOfficeSessionCreatedDTO
    {
        $boxOffice = $this->scopeService->loadBoxOffice($data->box_office_short_id);
        $current = $data->session;

        $occurrenceId = $data->event_occurrence_id === null
            ? $current->event_occurrence_id
            : $this->assertSwitchable($boxOffice, $data->event_occurrence_id);

        $reader = $data->change_reader
            ? $this->readerResolution->resolve($boxOffice->getEvent(), $data->stripe_terminal_reader_id)
            : null;
        $readerId = $data->change_reader ? $reader?->id : $current->stripe_terminal_reader_id;

        $scope = $this->scopeService->resolve($boxOffice, $occurrenceId);

        $session = new BoxOfficeSessionDTO(
            box_office_id: $current->box_office_id,
            event_id: $current->event_id,
            operator_name: $current->operator_name,
            event_occurrence_id: $scope->event_occurrence?->getId(),
            stripe_terminal_reader_id: $readerId,
            pin_hash: $current->pin_hash,
            expires_at: $current->expires_at,
            authenticated_user_id: $current->authenticated_user_id,
            authenticated_account_id: $current->authenticated_account_id,
        );

        $this->sessionService->update($data->token, $session);

        return new BoxOfficeSessionCreatedDTO(
            token: $data->token,
            expires_at: $session->expires_at,
            operator_name: $session->operator_name,
            event_occurrence: $scope->event_occurrence,
            reader: $reader !== null
                ? new BoxOfficeSessionReaderDTO(id: $reader->id, label: $reader->label)
                : $this->storedReader($readerId),
            check_in_list_short_id: $scope->check_in_list_short_id,
            check_in_available: $scope->check_in_available,
            check_in_unavailable_reason: $scope->check_in_unavailable_reason,
        );
    }

    /**
     * @throws ValidationException
     */
    private function assertSwitchable(BoxOfficeDomainObject $boxOffice, int $occurrenceId): int
    {
        if (! $this->scopeService->canSwitchOccurrence($boxOffice)) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('This box office sells a fixed date'),
            ]);
        }

        return $occurrenceId;
    }

    private function storedReader(?int $readerId): ?BoxOfficeSessionReaderDTO
    {
        if ($readerId === null) {
            return null;
        }

        $reader = $this->readerRepository->findFirstWhere(['id' => $readerId]);

        return $reader === null ? null : new BoxOfficeSessionReaderDTO(id: $reader->getId(), label: $reader->getLabel());
    }
}
