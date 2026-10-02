<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\SeatClaimDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatBlockResultDTO;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatSelectionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SkippedSeatBlockDTO;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Services\Domain\Order\DTO\ProcessedOrderItemDTO;
use Illuminate\Support\Collection;

class SeatClaimService
{
    public function __construct(
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly SeatedProductLookupService $seatedProductLookup,
        private readonly OrphanSeatRule $orphanSeatRule,
        private readonly CompanionSeatRule $companionSeatRule,
        private readonly SeatingEventLockService $seatingEventLock,
    ) {}

    /**
     * @param  Collection<int, ProcessedOrderItemDTO>  $items
     *
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     */
    public function claimForOrderItems(
        OrderDomainObject $order,
        Collection $items,
        bool $enforceSelectionRules = false,
        bool $allowBlocked = false,
    ): void {
        $this->claimForOrder(
            $order,
            $items->flatMap(fn (ProcessedOrderItemDTO $item) => array_map(
                fn (string $seatUid) => new SeatSelectionDTO(
                    seat_uid: $seatUid,
                    event_occurrence_id: $item->order_item->getEventOccurrenceId(),
                    product_id: $item->order_item->getProductId(),
                    product_price_id: $item->order_item->getProductPriceId(),
                    order_item_id: $item->order_item->getId(),
                ),
                $item->seat_uids,
            ))->values(),
            enforceSelectionRules: $enforceSelectionRules,
            allowBlocked: $allowBlocked,
        );
    }

    /**
     * @param  Collection<SeatSelectionDTO>  $selections
     *
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     */
    public function claimForOrder(
        OrderDomainObject $order,
        Collection $selections,
        bool $enforceSelectionRules = false,
        bool $allowBlocked = false,
    ): void {
        if ($selections->isEmpty()) {
            return;
        }

        $this->lockEvent($order->getEventId());

        $eventSeatMap = $this->eventSeatMapFor($order->getEventId());
        $index = $this->eventSeatMapLookup->indexFor($order->getEventId());
        $unsellable = $selections
            ->reject(fn (SeatSelectionDTO $selection) => $index->has($selection->seat_uid)
                && in_array($index->bandOf($selection->seat_uid), $this->seatedProductLookup->bandKeysFor($selection->product_id), true))
            ->pluck('seat_uid')
            ->unique()
            ->values()
            ->all();
        if ($unsellable !== []) {
            throw new SeatsUnavailableException($unsellable);
        }
        $this->assertSelectionIsConsistent($selections, $index);
        $lost = [];

        foreach ($selections->groupBy('event_occurrence_id') as $occurrenceId => $occurrenceSelections) {
            $seatUids = $occurrenceSelections
                ->pluck('seat_uid')
                ->reject(fn (string $uid) => $index->isZone($uid))
                ->values()
                ->all();

            $this->seatClaimRepository->deleteLongDeadClaims((int) $occurrenceId);
            if ($seatUids !== []) {
                $this->seatClaimRepository->deleteDeadClaimsForSeats([(int) $occurrenceId], $seatUids);
            }
            $holdReasons = $allowBlocked
                ? $this->seatClaimRepository->deleteBlocks([(int) $occurrenceId], $seatUids)->pluck('block_reason', 'seat_uid')
                : collect();

            if ($enforceSelectionRules && $eventSeatMap->getPreventOrphanSeats()) {
                $this->assertNoOrphanSeats((int) $occurrenceId, $seatUids, $index);
            }

            $fullZones = $this->fullZones((int) $occurrenceId, $occurrenceSelections, $index);
            $inserted = $this->seatClaimRepository->insertIgnoringConflicts(
                $occurrenceSelections
                    ->reject(fn (SeatSelectionDTO $selection) => in_array($selection->seat_uid, $fullZones, true))
                    ->map(fn (SeatSelectionDTO $selection) => $this->row($order, $selection, $index, $holdReasons))
                    ->values()
                    ->all(),
            );

            $lost = [...$lost, ...$fullZones, ...array_diff($seatUids, $inserted)];
        }

        if ($lost !== []) {
            throw new SeatsUnavailableException(array_values(array_unique($lost)));
        }
    }

    /**
     * @param  int[]  $occurrenceIds
     * @param  string[]  $seatUids
     *
     * @throws SeatSelectionInvalidException
     */
    public function block(int $eventId, array $occurrenceIds, array $seatUids, ?string $reason): SeatBlockResultDTO
    {
        $this->lockEvent($eventId);

        $index = $this->indexFor($eventId);
        foreach ($seatUids as $seatUid) {
            $this->assertIndividualSeat($index, $seatUid);
        }

        $this->seatClaimRepository->deleteDeadClaimsForSeats($occurrenceIds, $seatUids);
        $this->seatClaimRepository->deleteBlocks($occurrenceIds, $seatUids);

        $blocked = $this->seatClaimRepository->insertBlocks($eventId, $occurrenceIds, array_map(fn (string $seatUid) => [
            'seat_uid' => $seatUid,
            'band_key' => $index->bandOf($seatUid),
            'seat_label' => $index->labelOf($seatUid),
        ], $seatUids), $reason);
        $blockedByOccurrence = $blocked->groupBy('event_occurrence_id');

        return new SeatBlockResultDTO(
            blocked: $blocked->count(),
            skipped: collect($occurrenceIds)
                ->map(fn (int $occurrenceId) => new SkippedSeatBlockDTO(
                    event_occurrence_id: $occurrenceId,
                    seat_uids: array_values(array_diff($seatUids, $blockedByOccurrence->get($occurrenceId)?->pluck('seat_uid')->all() ?? [])),
                ))
                ->filter(fn (SkippedSeatBlockDTO $skipped) => $skipped->seat_uids !== [])
                ->values()
                ->all(),
        );
    }

    /**
     * @param  int[]  $occurrenceIds
     * @param  string[]  $seatUids
     */
    public function unblock(array $occurrenceIds, array $seatUids): int
    {
        return $this->seatClaimRepository->releaseBlocks($occurrenceIds, $seatUids);
    }

    /**
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     */
    public function move(
        AttendeeDomainObject $attendee,
        string $seatUid,
        bool $allowBlocked,
        bool $requireSameBand,
        bool $enforceSelectionRules = false,
    ): void {
        $this->lockEvent($attendee->getEventId());

        $claim = $this->seatClaimRepository->findFirstWhere([SeatClaimDomainObjectAbstract::ATTENDEE_ID => $attendee->getId()]);
        $index = $this->indexFor($attendee->getEventId());
        $this->assertIndividualSeat($index, $seatUid);

        $allowedBands = $requireSameBand ? [$claim?->getBandKey()] : $this->seatedProductLookup->bandKeysFor($attendee->getProductId());
        if ($claim === null || $claim->getIsZone() || ! in_array($index->bandOf($seatUid), $allowedBands, true)) {
            throw new SeatSelectionInvalidException(__('This attendee cannot be moved to that seat'));
        }
        if ($claim->getSeatUid() === $seatUid) {
            return;
        }

        $this->seatClaimRepository->deleteDeadClaimsForSeats([$claim->getEventOccurrenceId()], [$seatUid]);
        $hold = $allowBlocked
            ? $this->seatClaimRepository->deleteBlocks([$claim->getEventOccurrenceId()], [$seatUid])->first()
            : null;

        if (in_array($seatUid, $this->seatClaimRepository->findTakenSeatUids($claim->getEventOccurrenceId(), except: [$claim->getSeatUid()]), true)) {
            throw new SeatsUnavailableException([$seatUid]);
        }

        if ($enforceSelectionRules && $this->eventSeatMapFor($attendee->getEventId())->getPreventOrphanSeats()) {
            $this->assertNoOrphanSeats($claim->getEventOccurrenceId(), [$seatUid], $index, [$claim->getSeatUid()]);
        }

        if ($enforceSelectionRules) {
            $this->companionSeatRule->assertAccompanied($index, $this->orderSeatUidsAfterMove($claim, $seatUid));
        }

        $this->seatClaimRepository->updateFromArray($claim->getId(), [
            SeatClaimDomainObjectAbstract::SEAT_UID => $seatUid,
            SeatClaimDomainObjectAbstract::SEAT_LABEL => $index->labelOf($seatUid),
            SeatClaimDomainObjectAbstract::BAND_KEY => $index->bandOf($seatUid),
            SeatClaimDomainObjectAbstract::HELD_BACK => $hold !== null,
            SeatClaimDomainObjectAbstract::BLOCK_REASON => $hold?->block_reason,
        ]);
        if ($claim->getHeldBack()) {
            $this->seatClaimRepository->insertBlocks($claim->getEventId(), [$claim->getEventOccurrenceId()], [[
                'seat_uid' => $claim->getSeatUid(),
                'band_key' => $claim->getBandKey(),
                'seat_label' => $claim->getSeatLabel(),
            ]], $claim->getBlockReason());
        }
        $this->attendeeRepository->updateFromArray($attendee->getId(), [
            AttendeeDomainObjectAbstract::SEAT_UID => $seatUid,
            AttendeeDomainObjectAbstract::SEAT_LABEL => $index->labelOf($seatUid),
        ]);
    }

    public function releaseForAttendee(int $attendeeId): void
    {
        $this->seatClaimRepository->releaseForAttendees([$attendeeId]);
    }

    /**
     * @param  int[]  $attendeeIds
     */
    public function releaseForAttendees(array $attendeeIds): void
    {
        $this->seatClaimRepository->releaseForAttendees($attendeeIds);
    }

    /**
     * @throws SeatSelectionInvalidException
     */
    public function changeProductForAttendee(AttendeeDomainObject $attendee, int $productId, int $productPriceId): bool
    {
        $this->lockEvent($attendee->getEventId());

        $newProductBands = $this->seatedProductLookup->bandKeysFor($productId);
        $claim = $this->seatClaimRepository->findFirstWhere([SeatClaimDomainObjectAbstract::ATTENDEE_ID => $attendee->getId()]);

        if ($claim === null) {
            return $this->changeProductForReleasedSeat($attendee, $newProductBands);
        }

        if (! in_array($claim->getBandKey(), $newProductBands, true)) {
            throw new SeatSelectionInvalidException(__('This ticket cannot be used with the attendee\'s current seat'));
        }

        $this->seatClaimRepository->updateFromArray($claim->getId(), [
            SeatClaimDomainObjectAbstract::PRODUCT_ID => $productId,
            SeatClaimDomainObjectAbstract::PRODUCT_PRICE_ID => $productPriceId,
        ]);

        return true;
    }

    /**
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     */
    public function reclaimForAttendee(AttendeeDomainObject $attendee, OrderDomainObject $order): void
    {
        if ($attendee->getSeatUid() === null) {
            return;
        }

        $this->lockEvent($attendee->getEventId());
        $index = $this->indexFor($attendee->getEventId());
        $this->assertSeatFitsProduct($attendee, $index);

        $this->claimForOrder($order, collect([new SeatSelectionDTO(
            seat_uid: $attendee->getSeatUid(),
            event_occurrence_id: $attendee->getEventOccurrenceId(),
            product_id: $attendee->getProductId(),
            product_price_id: $attendee->getProductPriceId(),
            order_item_id: $this->orderItemIdFor($attendee, $index->bandOf($attendee->getSeatUid())),
            attendee_id: $attendee->getId(),
        )]));

        $this->attendeeRepository->updateFromArray($attendee->getId(), [
            AttendeeDomainObjectAbstract::SEAT_LABEL => $index->labelOf($attendee->getSeatUid()),
        ]);
    }

    /**
     * @return Collection<SeatClaimDomainObject>
     */
    public function claimsForOrder(int $orderId): Collection
    {
        return $this->seatClaimRepository->findWhere([SeatClaimDomainObjectAbstract::ORDER_ID => $orderId]);
    }

    public function pairWithAttendees(int $orderId): void
    {
        $claims = $this->claimsForOrder($orderId);
        $unpaired = $claims->filter(fn (SeatClaimDomainObject $claim) => $claim->getAttendeeId() === null);

        if ($unpaired->isEmpty()) {
            return;
        }

        $attendees = $this->attendeeRepository->findWhere([
            AttendeeDomainObjectAbstract::ORDER_ID => $orderId,
            [AttendeeDomainObjectAbstract::SEAT_UID, 'not null', null],
            [AttendeeDomainObjectAbstract::STATUS, '!=', AttendeeStatus::CANCELLED->name],
        ]);

        $claimedAttendeeIds = $claims
            ->map(fn (SeatClaimDomainObject $claim) => $claim->getAttendeeId())
            ->filter()
            ->all();

        /** @var AttendeeDomainObject $attendee */
        foreach ($attendees as $attendee) {
            if (in_array($attendee->getId(), $claimedAttendeeIds, true)) {
                continue;
            }

            $key = $unpaired->search(fn (SeatClaimDomainObject $claim) => $claim->getSeatUid() === $attendee->getSeatUid()
                && $claim->getEventOccurrenceId() === $attendee->getEventOccurrenceId()
                && $claim->getProductPriceId() === $attendee->getProductPriceId());

            if ($key === false) {
                continue;
            }

            $this->seatClaimRepository->updateFromArray($unpaired->get($key)->getId(), [
                SeatClaimDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
            ]);
            $claimedAttendeeIds[] = $attendee->getId();
            $unpaired->forget($key);
        }
    }

    /**
     * @param  string[]  $newProductBands
     *
     * @throws SeatSelectionInvalidException
     */
    private function changeProductForReleasedSeat(AttendeeDomainObject $attendee, array $newProductBands): bool
    {
        if ($newProductBands === [] && $attendee->getSeatUid() !== null) {
            $this->attendeeRepository->updateFromArray($attendee->getId(), [
                AttendeeDomainObjectAbstract::SEAT_UID => null,
                AttendeeDomainObjectAbstract::SEAT_LABEL => null,
            ]);
        }

        if ($newProductBands === []) {
            return false;
        }

        $index = $this->indexFor($attendee->getEventId());
        if ($attendee->getSeatUid() === null
            || ! $index->has($attendee->getSeatUid())
            || ! in_array($index->bandOf($attendee->getSeatUid()), $newProductBands, true)) {
            throw new SeatSelectionInvalidException(__('This ticket cannot be used with the attendee\'s current seat'));
        }

        return true;
    }

    private function orderItemIdFor(AttendeeDomainObject $attendee, ?string $bandKey): ?int
    {
        $items = $this->orderItemRepository->findWhere([
            OrderItemDomainObjectAbstract::ORDER_ID => $attendee->getOrderId(),
            OrderItemDomainObjectAbstract::PRODUCT_PRICE_ID => $attendee->getProductPriceId(),
            OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID => $attendee->getEventOccurrenceId(),
        ]);

        return ($items->first(fn (OrderItemDomainObject $item) => $item->getBandKey() === $bandKey) ?? $items->first())?->getId();
    }

    /**
     * @throws SeatSelectionInvalidException
     */
    private function assertSeatFitsProduct(AttendeeDomainObject $attendee, SeatMapIndex $index): void
    {
        $seatUid = $attendee->getSeatUid();

        if (! $index->has($seatUid) || ! in_array($index->bandOf($seatUid), $this->seatedProductLookup->bandKeysFor($attendee->getProductId()), true)) {
            throw new SeatSelectionInvalidException(__('Seat :seat is no longer on the seat map for this ticket', ['seat' => $attendee->getSeatLabel()]));
        }
    }

    private function lockEvent(int $eventId): void
    {
        $this->seatingEventLock->lock($eventId);
    }

    /**
     * @throws SeatSelectionInvalidException
     */
    private function eventSeatMapFor(int $eventId): EventSeatMapDomainObject
    {
        return $this->eventSeatMapLookup->findForEvent($eventId)
            ?? throw new SeatSelectionInvalidException(__('This event no longer has a seat map'));
    }

    /**
     * @throws SeatSelectionInvalidException
     */
    private function indexFor(int $eventId): SeatMapIndex
    {
        $this->eventSeatMapFor($eventId);

        return $this->eventSeatMapLookup->indexFor($eventId);
    }

    /**
     * @throws SeatSelectionInvalidException
     */
    private function assertIndividualSeat(SeatMapIndex $index, string $seatUid): void
    {
        if (! $index->has($seatUid) || $index->isZone($seatUid)) {
            throw new SeatSelectionInvalidException(__('That seat is not on the seat map'));
        }
    }

    /**
     * @param  Collection<SeatSelectionDTO>  $selections
     * @return string[]
     */
    private function fullZones(int $occurrenceId, Collection $selections, SeatMapIndex $index): array
    {
        return $selections
            ->pluck('seat_uid')
            ->filter(fn (string $uid) => $index->isZone($uid))
            ->countBy()
            ->filter(fn (int $requested, string $zoneUid) => $this->seatClaimRepository->countLiveForZone($occurrenceId, $zoneUid) + $requested > $index->zoneCapacity($zoneUid))
            ->keys()
            ->all();
    }

    /**
     * @param  Collection<string, ?string>  $holdReasons
     */
    private function row(OrderDomainObject $order, SeatSelectionDTO $selection, SeatMapIndex $index, Collection $holdReasons): array
    {
        return [
            SeatClaimDomainObjectAbstract::EVENT_ID => $order->getEventId(),
            SeatClaimDomainObjectAbstract::EVENT_OCCURRENCE_ID => $selection->event_occurrence_id,
            SeatClaimDomainObjectAbstract::SEAT_UID => $selection->seat_uid,
            SeatClaimDomainObjectAbstract::IS_ZONE => $index->isZone($selection->seat_uid) ? 'true' : 'false',
            SeatClaimDomainObjectAbstract::BAND_KEY => $index->bandOf($selection->seat_uid),
            SeatClaimDomainObjectAbstract::SEAT_LABEL => $index->labelOf($selection->seat_uid),
            SeatClaimDomainObjectAbstract::ORDER_ID => $order->getId(),
            SeatClaimDomainObjectAbstract::PRODUCT_ID => $selection->product_id,
            SeatClaimDomainObjectAbstract::PRODUCT_PRICE_ID => $selection->product_price_id,
            SeatClaimDomainObjectAbstract::ORDER_ITEM_ID => $selection->order_item_id,
            SeatClaimDomainObjectAbstract::ATTENDEE_ID => $selection->attendee_id,
            SeatClaimDomainObjectAbstract::HELD_BACK => $holdReasons->has($selection->seat_uid) ? 'true' : 'false',
            SeatClaimDomainObjectAbstract::BLOCK_REASON => $holdReasons->get($selection->seat_uid),
            SeatClaimDomainObjectAbstract::CREATED_AT => now()->toDateTimeString(),
            SeatClaimDomainObjectAbstract::UPDATED_AT => now()->toDateTimeString(),
        ];
    }

    /**
     * @param  Collection<SeatSelectionDTO>  $selections
     *
     * @throws SeatSelectionInvalidException
     */
    private function assertSelectionIsConsistent(Collection $selections, SeatMapIndex $index): void
    {
        $repeatsASeat = $selections
            ->reject(fn (SeatSelectionDTO $selection) => $index->isZone($selection->seat_uid))
            ->groupBy('event_occurrence_id')
            ->contains(fn (Collection $occurrenceSeats) => $occurrenceSeats->pluck('seat_uid')->duplicates()->isNotEmpty());
        if ($repeatsASeat) {
            throw new SeatSelectionInvalidException(__('The same seat was selected more than once'));
        }

        $mixesBands = $selections
            ->whereNotNull('order_item_id')
            ->groupBy('order_item_id')
            ->contains(fn (Collection $itemSeats) => $itemSeats->map(fn (SeatSelectionDTO $selection) => $index->bandOf($selection->seat_uid))->unique()->count() > 1);
        if ($mixesBands) {
            throw new SeatSelectionInvalidException(__('Seats from different price bands cannot share one ticket line'));
        }
    }

    /**
     * @param  string[]  $seatUids
     * @param  string[]  $releasedSeatUids
     *
     * @throws SeatSelectionInvalidException
     */
    private function assertNoOrphanSeats(int $occurrenceId, array $seatUids, SeatMapIndex $index, array $releasedSeatUids = []): void
    {
        $orphans = $this->orphanSeatRule->findOrphans(
            $index->segments(),
            $this->seatClaimRepository->findTakenSeatUids($occurrenceId, except: $releasedSeatUids),
            $seatUids,
        );

        if ($orphans !== []) {
            throw new SeatSelectionInvalidException(__('Please do not leave a single empty seat: :seats', [
                'seats' => implode(', ', array_map(fn (string $uid) => $index->labelOf($uid), $orphans)),
            ]));
        }
    }

    /**
     * @return string[]
     */
    private function orderSeatUidsAfterMove(SeatClaimDomainObject $movedClaim, string $seatUid): array
    {
        return $this->seatClaimRepository->findWhere([
            SeatClaimDomainObjectAbstract::ORDER_ID => $movedClaim->getOrderId(),
            SeatClaimDomainObjectAbstract::EVENT_OCCURRENCE_ID => $movedClaim->getEventOccurrenceId(),
        ])
            ->map(fn (SeatClaimDomainObject $claim) => $claim->getId() === $movedClaim->getId() ? $seatUid : $claim->getSeatUid())
            ->values()
            ->all();
    }
}
