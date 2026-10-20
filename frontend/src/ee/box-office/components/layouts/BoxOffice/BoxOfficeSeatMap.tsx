import {useMemo, useState} from "react";
import {Alert, Button, Loader, Modal} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconSparkles} from "@tabler/icons-react";
import {PublicEventSeatMap} from "../../../../seating/api/seat-map.client.ts";
import {publicBoxOfficeClient} from "../../../api/box-office-public.client.ts";
import {useGetBoxOfficeOccupiedSeats} from "../../../queries/useGetBoxOfficeSeating.ts";
import {BoxOfficeCart, lineCap, openSeatCount, SeatAssignment, seatPrice} from "../../../hooks/useBoxOfficeCart.ts";
import {showError, showInfo} from "../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {
    fillOpenTickets,
    openSeatsInBand,
    SeatedType,
    seatedTypeTitle,
    seatsToChoose,
} from "../../../utilites/boxOfficeSeating.ts";
import {bandOf, indexLayout, seatLabel} from "../../../../seating/components/lib/layoutIndex.ts";
import {useSeatStates} from "../../../../seating/components/lib/useSeatStates.ts";
import {AreaTabs} from "../../../../seating/components/AreaTabs.tsx";
import {SeatMapRenderer} from "../../../../seating/components/SeatMapRenderer";
import {formatPrice} from "../../../../seating/components/SeatPicker/ticketOptions.ts";
import {occupancyExcludingSeats, occupancyFromOccupiedSeats} from "../../../../seating/components/SeatChooser/occupancy.ts";
import classes from "./BoxOfficeSeatMap.module.scss";

interface BoxOfficeSeatMapProps {
    boxOfficeShortId: string;
    seatMap: PublicEventSeatMap;
    occupiedSeatsQuery: ReturnType<typeof useGetBoxOfficeOccupiedSeats>;
    cart: BoxOfficeCart;
    types: SeatedType[];
    typesByPrice: Map<number, SeatedType>;
    currency: string;
}

interface PendingChoice {
    bandKey: string;
    seatUids: string[];
}

export const BoxOfficeSeatMap = ({
    boxOfficeShortId,
    seatMap,
    occupiedSeatsQuery,
    cart,
    types,
    typesByPrice,
    currency,
}: BoxOfficeSeatMapProps) => {
    const occupiedSeats = occupiedSeatsQuery.data?.seats;
    const occupancyUnavailable = occupiedSeats === undefined;
    const index = useMemo(() => indexLayout(seatMap.layout), [seatMap.layout]);
    const [areaId, setAreaId] = useState(seatMap.layout.areas[0].id);
    const [heldSeat, setHeldSeat] = useState<{uid: string; reason: string | null} | null>(null);
    const [typeChoice, setTypeChoice] = useState<PendingChoice | null>(null);
    const [isFinding, setIsFinding] = useState(false);
    const area = seatMap.layout.areas.find(candidate => candidate.id === areaId) ?? seatMap.layout.areas[0];

    const cartSeatUids = useMemo(() => cart.lines.flatMap(line => line.seatUids ?? []), [cart.lines]);
    const cartSeats = useMemo(() => new Set(cartSeatUids), [cartSeatUids]);
    const occupancy = useMemo(
        () => occupancyExcludingSeats(occupancyFromOccupiedSeats(seatMap.layout, occupiedSeats ?? []), cartSeatUids),
        [seatMap.layout, occupiedSeats, cartSeatUids],
    );
    const blockReasons = useMemo(() => new Map((occupiedSeats ?? [])
        .filter(seat => seat.status === 'BLOCKED')
        .map(seat => [seat.seat_uid, seat.block_reason])), [occupiedSeats]);
    const sellableBands = useMemo(() => new Set(types.flatMap(type => [...type.bandKeys])), [types]);
    const bandsWithSeats = useMemo(() => new Set([...index.seats.values()].map(({seat}) => seat.band)), [index]);
    const toChoose = seatsToChoose(cart.lines, typesByPrice);

    const seatStates = useSeatStates(seatMap.layout.areas, {
        selected: cartSeats,
        blocked: occupancy.blocked,
        unavailable: occupancy.unavailable,
        sellableBands,
    });

    const zoneSelected = useMemo(() => cartSeatUids.reduce<Record<string, number>>((counts, uid) => (
        index.zones.has(uid) ? {...counts, [uid]: (counts[uid] ?? 0) + 1} : counts
    ), {}), [cartSeatUids, index]);

    const announce = (seatUids: string[]) => {
        new Set(seatUids).forEach(uid => {
            const seat = index.seats.get(uid)?.seat;
            const label = seatLabel(index, uid);
            if (seat?.acc) showInfo(t`${label} is a wheelchair space`);
            if (seat?.comp) showInfo(t`${label} is a companion seat for someone accompanying a wheelchair user`);
            if (seat?.note) showInfo(`${label}: ${seat.note}`);
        });
    };

    const assign = (assignments: SeatAssignment[]) => {
        const overLimit = assignments.find(({product, price, seatUids}) => {
            const line = cart.state.lines[price.id];
            return Math.max(line?.quantity ?? 0, (line?.seatUids?.length ?? 0) + seatUids.length) > lineCap(product, price, true);
        });
        if (overLimit) {
            showError(t`${overLimit.product.title} is limited to ${lineCap(overLimit.product, overLimit.price, true)} per order`);
            return;
        }
        cart.assignSeats(assignments);
        announce(assignments.flatMap(assignment => assignment.seatUids));
    };

    const place = (bandKey: string, seatUids: string[]) => {
        const {assignments, leftover} = fillOpenTickets(cart.lines, typesByPrice, bandKey, seatUids);
        if (leftover.length === 0) {
            assign(assignments);
            return;
        }
        const candidates = types.filter(type => type.bandKeys.has(bandKey));
        if (candidates.length === 1) {
            assign([...assignments, {product: candidates[0].product, price: candidates[0].price, seatUids: leftover}]);
            return;
        }
        if (assignments.length > 0) assign(assignments);
        setTypeChoice({bandKey, seatUids: leftover});
    };

    const handleSeat = (uid: string, confirmedHeld = false) => {
        if (isFinding) return;
        if (cartSeats.has(uid)) {
            cart.removeSeat(uid);
            return;
        }
        const bandKey = bandOf(index, uid);
        if (!bandKey || !sellableBands.has(bandKey) || occupancy.unavailable.has(uid)) return;
        if (occupancy.blocked.has(uid) && !confirmedHeld) {
            setHeldSeat({uid, reason: blockReasons.get(uid) ?? null});
            return;
        }
        place(bandKey, [uid]);
    };

    const handleZone = (uid: string) => {
        if (isFinding) return;
        const zone = index.zones.get(uid)?.zone;
        const remaining = occupancy.zoneRemaining[uid] ?? 0;
        if (!zone || !sellableBands.has(zone.band) || remaining === 0) return;
        const open = openSeatsInBand(cart.lines, typesByPrice, zone.band);
        place(zone.band, Array(open > 0 ? Math.min(open, remaining) : 1).fill(uid));
    };

    const fillFromZones = (bandKeys: Set<string>, quantity: number, claimed: Record<string, number>): string[] | null => {
        const zones = [...index.zones.values()]
            .filter(({zone}) => bandKeys.has(zone.band))
            .map(({zone}) => ({uid: zone.id, free: (occupancy.zoneRemaining[zone.id] ?? 0) - (claimed[zone.id] ?? 0)}))
            .sort((a, b) => b.free - a.free);
        const best = zones[0];
        if (!best || best.free < quantity) return null;
        claimed[best.uid] = (claimed[best.uid] ?? 0) + quantity;
        return Array(quantity).fill(best.uid);
    };

    const bestAvailable = async () => {
        const openLines = cart.lines.filter(line => typesByPrice.has(line.priceId) && openSeatCount(line) > 0);
        const hasSeats = (line: typeof openLines[number]) => [...typesByPrice.get(line.priceId)!.bandKeys].some(band => bandsWithSeats.has(band));
        const seatLines = openLines.filter(hasSeats);
        const zoneLines = openLines.filter(line => !hasSeats(line));
        const assignments: SeatAssignment[] = [];
        const shortfalls: string[] = [];
        const claimedZonePlaces: Record<string, number> = {};

        zoneLines.forEach(line => {
            const type = typesByPrice.get(line.priceId)!;
            const places = fillFromZones(type.bandKeys, openSeatCount(line), claimedZonePlaces);
            if (places) {
                assignments.push({product: type.product, price: type.price, seatUids: places});
            } else {
                shortfalls.push(seatedTypeTitle(type));
            }
        });

        if (seatLines.length > 0) {
            setIsFinding(true);
            try {
                const {data} = await publicBoxOfficeClient.getBestAvailableSeats(
                    boxOfficeShortId,
                    seatLines.map(line => ({product_id: line.productId, quantity: openSeatCount(line)})),
                    cartSeatUids.filter(uid => index.seats.has(uid)),
                );
                seatLines.forEach((line, position) => {
                    const type = typesByPrice.get(line.priceId)!;
                    const found = data.seat_uids[position] ?? [];
                    if (found.length === openSeatCount(line)) {
                        assignments.push({product: type.product, price: type.price, seatUids: found});
                    } else {
                        shortfalls.push(seatedTypeTitle(type));
                    }
                });
            } catch (error) {
                showError(firstApiError(error, t`Unable to find seats. Check the connection and try again.`));
                return;
            } finally {
                setIsFinding(false);
            }
        }

        if (assignments.length > 0) assign(assignments);
        if (shortfalls.length > 0) showError(t`Not enough free seats for ${shortfalls.join(', ')}`);
    };

    const seatsInArea = (candidateAreaId: string) => cartSeatUids.filter(uid =>
        (index.seats.get(uid)?.area.id ?? index.zones.get(uid)?.area.id) === candidateAreaId).length;

    const choiceTypes = typeChoice ? types.filter(type => type.bandKeys.has(typeChoice.bandKey)) : [];

    if (occupiedSeatsQuery.isLoading) {
        return (
            <div className={classes.panel} data-testid="box-office-seat-map">
                <Loader size="sm" style={{margin: '40px auto'}}/>
            </div>
        );
    }

    if (occupancyUnavailable) {
        return (
            <div className={classes.panel} data-testid="box-office-seat-map">
                <Alert color="red" variant="light" title={t`Seat availability unavailable`}
                       data-testid="box-office-seat-availability-error">
                    <div className={classes.dialog}>
                        <p className={classes.dialogText}>
                            {t`Seats cannot be chosen until the latest availability loads.`}
                        </p>
                        <div>
                            <Button size="xs" variant="default" loading={occupiedSeatsQuery.isFetching}
                                    onClick={() => occupiedSeatsQuery.refetch()}
                                    data-testid="box-office-seat-availability-retry-button">
                                {t`Retry`}
                            </Button>
                        </div>
                    </div>
                </Alert>
            </div>
        );
    }

    return (
        <div className={classes.panel} data-testid="box-office-seat-map">
            <div className={classes.toolbar}>
                <div className={classes.status} data-testid="box-office-seats-to-choose">
                    {toChoose > 0
                        ? (toChoose === 1 ? t`Choose 1 seat` : <Trans>Choose {toChoose} seats</Trans>)
                        : t`Tap a seat to add it, or set counts first`}
                </div>
                {toChoose > 0 && (
                    <Button size="sm" leftSection={<IconSparkles size={16}/>} loading={isFinding} onClick={bestAvailable}
                            data-testid="box-office-best-available-button">
                        {t`Best available`}
                    </Button>
                )}
            </div>

            {occupiedSeatsQuery.isError && (
                <div className={classes.retrying}>
                    <Loader size={12}/>
                    {t`Couldn't refresh seat availability. Retrying…`}
                </div>
            )}

            <AreaTabs areas={seatMap.layout.areas} value={area.id} onChange={setAreaId} badge={seatsInArea}
                      className={classes.areas}/>

            <div className={classes.map} data-finding={isFinding || undefined}>
                <SeatMapRenderer area={area} bands={index.bands} seatStates={seatStates} interactive wheelZoom="always"
                                 zoneRemaining={occupancy.zoneRemaining} zoneSelected={zoneSelected}
                                 onSeatClick={uid => handleSeat(uid)} onZoneClick={handleZone}/>
            </div>

            <Modal opened={heldSeat !== null} onClose={() => setHeldSeat(null)} title={t`This seat is held back`} centered size="sm">
                {heldSeat && (
                    <div className={classes.dialog}>
                        <p className={classes.dialogText}>
                            <strong>{seatLabel(index, heldSeat.uid)}</strong>
                            {heldSeat.reason ? ` · ${heldSeat.reason}` : ''}
                        </p>
                        <p className={classes.dialogText}>{t`Held-back seats are kept off sale. Only sell it if you are sure.`}</p>
                        <div className={classes.dialogActions}>
                            <Button variant="default" onClick={() => setHeldSeat(null)}>{t`Cancel`}</Button>
                            <Button color="orange" data-testid="box-office-held-seat-confirm" onClick={() => {
                                handleSeat(heldSeat.uid, true);
                                setHeldSeat(null);
                            }}>
                                {t`Sell anyway`}
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal opened={typeChoice !== null} onClose={() => setTypeChoice(null)} centered size="sm"
                   title={typeChoice ? t`Which ticket for ${[...new Set(typeChoice.seatUids)].map(uid => seatLabel(index, uid)).join(', ')}?` : ''}>
                <div className={classes.typeList}>
                    {choiceTypes.map(type => (
                        <button key={type.price.id} type="button" className={classes.typeOption}
                                data-testid={`box-office-seat-type-${type.price.id}`}
                                onClick={() => {
                                    assign([{product: type.product, price: type.price, seatUids: typeChoice!.seatUids}]);
                                    setTypeChoice(null);
                                }}>
                            <span>{seatedTypeTitle(type)}</span>
                            <span className={classes.typePrice}>
                                {formatPrice(seatPrice(type.price, typeChoice?.bandKey), currency)}
                            </span>
                        </button>
                    ))}
                </div>
            </Modal>
        </div>
    );
};
