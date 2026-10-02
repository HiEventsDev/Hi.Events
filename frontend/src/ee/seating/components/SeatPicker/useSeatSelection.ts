import {useEffect, useMemo, useState} from "react";
import {useMutation} from "@tanstack/react-query";
import {t} from "@lingui/macro";
import {IdParam, Product} from "../../../../types.ts";
import {PublicEventSeatMap, seatMapClientPublic} from "../../api/seat-map.client.ts";
import {useGetSeatAvailability} from "../../queries/useGetSeatAvailability.ts";
import {showError, showInfo} from "../../../../utilites/notifications.tsx";
import {excessCompanionSeats} from "../lib/companionRule.ts";
import {bandOf, companionRuleSeats, hasCompanionSeats, indexLayout, seatLabel} from "../lib/layoutIndex.ts";
import {findOrphanSeats} from "../lib/orphanRule.ts";
import {seatStateOf, useSeatStates} from "../lib/useSeatStates.ts";
import {BasketLine} from "./Basket.tsx";
import {SeatSheetTarget} from "./SeatSheet.tsx";
import {
    bandKeysForProduct,
    bandUnavailableReason,
    BandUnavailableReason,
    choicesTotal,
    isBandSoldOut,
    linkedBandKeys,
    SeatChoice,
    seatedProductIds,
    TicketOption,
    ticketOptionsByBand,
} from "./ticketOptions.ts";

interface SeatSelectionOptions {
    eventId: IdParam;
    occurrenceId: IdParam | undefined;
    seatMap: PublicEventSeatMap;
    products: Product[];
    choices: SeatChoice[];
    lostSeatUids: string[];
    areaId: string | undefined;
    isActive: boolean;
    onChange: (choices: SeatChoice[]) => void;
    onAreaChange: (areaId: string) => void;
}

const lineKey = (choice: SeatChoice, position: number) => `${choice.seat_uid}-${position}`;

const countForProduct = (choices: SeatChoice[], productId: number): number =>
    choices.filter(choice => choice.product_id === productId).length;

export interface SeatedTicketType {
    product: Product;
}

const cheapestPerPrice = (options: Map<string, TicketOption[]>): TicketOption[] => {
    const cheapest = new Map<number, TicketOption>();
    [...options.values()].flat().forEach(option => {
        const existing = cheapest.get(option.price_id);
        if (!existing || option.price < existing.price) {
            cheapest.set(option.price_id, option);
        }
    });
    return [...cheapest.values()];
};

export const useSeatSelection = (props: SeatSelectionOptions) => {
    const {seatMap, choices, onChange} = props;
    const index = useMemo(() => indexLayout(seatMap.layout), [seatMap.layout]);
    const options = useMemo(
        () => ticketOptionsByBand(seatMap.band_products, props.products),
        [seatMap.band_products, props.products],
    );
    const [sheetTarget, setSheetTarget] = useState<SeatSheetTarget | null>(null);
    const [preferredPriceId, setPreferredPriceId] = useState<number | null>(null);
    const productsById = useMemo(
        () => new Map(props.products.map(product => [Number(product.id), product])),
        [props.products],
    );

    const limitMessage = (productId: number, baseChoices: SeatChoice[], adding: number): string | null => {
        const product = productsById.get(productId);
        const max = product?.max_per_order;
        if (!product || !max || countForProduct(baseChoices, productId) + adding <= max) {
            return null;
        }
        const title = product.title;
        return t`You can choose up to ${max} ${title} tickets per order`;
    };

    const preferredFirst = (candidates: TicketOption[]): TicketOption[] => [
        ...candidates.filter(option => option.price_id === preferredPriceId),
        ...candidates.filter(option => option.price_id !== preferredPriceId),
    ];

    const firstAddable = (candidates: TicketOption[], baseChoices: SeatChoice[], adding: number): TicketOption | undefined =>
        preferredFirst(candidates).find(option => limitMessage(option.product_id, baseChoices, adding) === null);

    const availabilityQuery = useGetSeatAvailability(props.eventId, props.occurrenceId, props.isActive, seatMap.version);
    const availability = availabilityQuery.data;
    const unavailable = useMemo(() => new Set(availability?.unavailable_seat_uids ?? []), [availability]);
    const chosenSeatUids = useMemo(() => new Set(choices.map(choice => choice.seat_uid)), [choices]);
    const sellableBands = useMemo(
        () => new Set([...options].filter(([, bandOptions]) => bandOptions.length > 0).map(([bandKey]) => bandKey)),
        [options],
    );
    const seatStates = useSeatStates(seatMap.layout.areas, {selected: chosenSeatUids, unavailable, sellableBands});

    useEffect(() => {
        if (props.lostSeatUids.length > 0) {
            availabilityQuery.refetch();
        }
    }, [props.lostSeatUids]);

    const area = seatMap.layout.areas.find(candidate => candidate.id === props.areaId) ?? seatMap.layout.areas[0];
    const optionsFor = (uid: string): TicketOption[] => options.get(bandOf(index, uid) ?? '') ?? [];

    const zoneSelected = useMemo(() => {
        const counts: Record<string, number> = {};
        choices.filter(choice => index.zones.has(choice.seat_uid))
            .forEach(choice => counts[choice.seat_uid] = (counts[choice.seat_uid] ?? 0) + 1);
        return counts;
    }, [choices, index]);

    const lines: BasketLine[] = useMemo(() => choices.map((choice, position) => ({
        key: lineKey(choice, position),
        label: seatLabel(index, choice.seat_uid),
        choice,
        options: options.get(bandOf(index, choice.seat_uid) ?? '') ?? [],
    })), [choices, index, options]);

    const total = useMemo(() => choicesTotal(choices, options), [choices, options]);

    const orphanLabels = useMemo(() => seatMap.prevent_orphan_seats
        ? findOrphanSeats(index.segments, [...unavailable], [...chosenSeatUids]).map(uid => seatLabel(index, uid))
        : [], [seatMap.prevent_orphan_seats, index, unavailable, chosenSeatUids]);

    const isCompanionBlocked = (seatUids: string[]) => excessCompanionSeats(companionRuleSeats(index, seatUids)) > 0;
    const hasUnaccompaniedCompanions = useMemo(
        () => isCompanionBlocked([...chosenSeatUids]),
        [index, chosenSeatUids],
    );
    const mapHasCompanionSeats = useMemo(() => hasCompanionSeats(index), [index]);

    const allOptions = useMemo(() => cheapestPerPrice(options), [options]);
    const linkedBands = useMemo(() => linkedBandKeys(seatMap.band_products), [seatMap.band_products]);
    const ticketTypesShareBands = useMemo(() => {
        const bandSets = [...seatedProductIds(seatMap.band_products)]
            .map(productId => [...bandKeysForProduct(seatMap.band_products, productId)].sort().join('|'));
        return new Set(bandSets).size <= 1;
    }, [seatMap.band_products]);
    const isSoldOut = [...linkedBands].every(
        bandKey => isBandSoldOut(options.get(bandKey) ?? [], availability?.band_free[bandKey]),
    );
    const bestAvailableOptions = isSoldOut || (choices.length > 0 && props.lostSeatUids.length === 0)
        ? []
        : ticketTypesShareBands ? preferredFirst(allOptions).slice(0, 1) : allOptions;
    const unavailableReasons = useMemo(() => Object.fromEntries([...linkedBands].map(bandKey => [
        bandKey,
        bandUnavailableReason(bandKey, seatMap.band_products, props.products),
    ])) as Record<string, BandUnavailableReason>, [linkedBands, seatMap.band_products, props.products]);
    const ticketTypes: SeatedTicketType[] = useMemo(() => {
        const seatedIds = seatedProductIds(seatMap.band_products);
        return props.products
            .filter(product => seatedIds.has(Number(product.id)))
            .map(product => ({product}));
    }, [props.products, seatMap.band_products]);
    const minimumWarnings = useMemo(() => ticketTypes.flatMap(({product}) => {
        const chosen = countForProduct(choices, Number(product.id));
        const min = product.min_per_order ?? 1;
        const title = product.title;
        return chosen > 0 && chosen < min ? [t`Choose at least ${min} ${title} tickets`] : [];
    }), [ticketTypes, choices]);
    const legendBands = useMemo(
        () => seatMap.layout.bands.filter(band => linkedBands.has(band.key)),
        [seatMap.layout.bands, linkedBands],
    );

    const hasRoomFor = (additional: number): boolean => {
        const max = seatMap.max_seats_per_order;
        if (max !== null && choices.length + additional > max) {
            showInfo(t`You can choose up to ${max} seats per order`);
            return false;
        }
        return true;
    };

    const addChoices = (uid: string, option: TicketOption, quantity: number) => {
        const kept = choices.filter(choice => choice.seat_uid !== uid);
        const limit = limitMessage(option.product_id, kept, quantity);
        if (limit) {
            showInfo(limit);
            return;
        }
        setPreferredPriceId(option.price_id);
        onChange([...kept, ...Array.from({length: quantity}, () => ({
            seat_uid: uid,
            product_id: option.product_id,
            price_id: option.price_id,
            band_key: bandOf(index, uid) ?? null,
        }))]);
        setSheetTarget(null);
    };

    const confirmSheet = (uid: string, quantity: number) => {
        const kept = choices.filter(choice => choice.seat_uid !== uid);
        const sheetOptions = optionsFor(uid);
        const option = firstAddable(sheetOptions, kept, quantity);
        if (!option) {
            showInfo(limitMessage(sheetOptions[0]?.product_id ?? 0, kept, quantity) ?? '');
            return;
        }
        addChoices(uid, option, quantity);
    };

    const handleSeatClick = (uid: string) => {
        if (chosenSeatUids.has(uid)) {
            onChange(choices.filter(choice => choice.seat_uid !== uid));
            return;
        }

        const indexed = index.seats.get(uid);
        const seatOptions = optionsFor(uid);
        if (!indexed || seatStateOf(seatStates, uid) === 'unavailable' || !hasRoomFor(1)) {
            return;
        }

        if (!indexed.seat.acc && !indexed.seat.comp && !indexed.seat.note) {
            const option = firstAddable(seatOptions, choices, 1);
            if (option) {
                addChoices(uid, option, 1);
            } else if (seatOptions.length > 0) {
                showInfo(limitMessage(seatOptions[0].product_id, choices, 1) ?? '');
            }
            return;
        }

        setSheetTarget({
            uid,
            label: seatLabel(index, uid),
            note: indexed.seat.note,
            accessible: indexed.seat.acc,
            companion: indexed.seat.comp,
            needsWheelchairSpace: indexed.seat.comp && isCompanionBlocked([...chosenSeatUids, uid]),
            options: preferredFirst(seatOptions),
        });
    };

    const handleZoneClick = (uid: string) => {
        const remaining = (availability?.zone_remaining[uid] ?? 0);
        const zoneOptions = optionsFor(uid);
        if (zoneOptions.length === 0 || remaining <= 0 || !hasRoomFor(1)) {
            return;
        }
        const max = seatMap.max_seats_per_order;
        setSheetTarget({
            uid,
            label: seatLabel(index, uid),
            note: null,
            accessible: false,
            companion: false,
            needsWheelchairSpace: false,
            options: preferredFirst(zoneOptions),
            zoneRemaining: max === null ? remaining : Math.min(remaining, max - choices.length + (zoneSelected[uid] ?? 0)),
        });
    };

    const bestAvailableMutation = useMutation({
        mutationFn: ({option, quantity}: {option: TicketOption; quantity: number}) => seatMapClientPublic.getBestAvailable(
            props.eventId,
            props.occurrenceId as IdParam,
            {product_id: option.product_id, quantity, exclude: props.lostSeatUids},
        ).then(response => ({seatUids: response.data.seat_uids, option})),
        onSuccess: ({seatUids, option}) => {
            if (seatUids.length === 0) {
                showError(t`We couldn't find that many seats together. Try fewer seats or pick them on the map.`);
                return;
            }
            const zoneChoices = choices.filter(choice => index.zones.has(choice.seat_uid));
            onChange([...zoneChoices, ...seatUids.map(seat_uid => ({
                seat_uid,
                product_id: option.product_id,
                price_id: option.price_id,
                band_key: bandOf(index, seat_uid) ?? null,
            }))]);
            const firstArea = index.seats.get(seatUids[0])?.area.id;
            if (firstArea) {
                props.onAreaChange(firstArea);
            }
        },
        onError: (error: any) => showError(
            error?.response?.data?.errors?.quantity?.[0]
            ?? error?.response?.data?.message
            ?? t`We couldn't find seats right now. Please try again.`,
        ),
    });

    return {
        index,
        options,
        area,
        availability,
        seatStates,
        zoneSelected,
        sheetTarget,
        setSheetTarget,
        confirmSheet,
        handleSeatClick,
        handleZoneClick,
        lines,
        total,
        orphanLabels,
        hasUnaccompaniedCompanions,
        hasCompanionSeats: mapHasCompanionSeats,
        lostLabels: props.lostSeatUids.map(uid => seatLabel(index, uid)),
        legendBands,
        bestAvailableOptions,
        isSoldOut,
        hasSeatsForSale: linkedBands.size > 0,
        unavailableReasons,
        ticketTypes,
        minimumWarnings,
        isFindingSeats: bestAvailableMutation.isPending,
        maxSeatsPerOrder: seatMap.max_seats_per_order,
        findBestAvailable: (option: TicketOption, quantity: number) => {
            const kept = choices.filter(choice => index.zones.has(choice.seat_uid));
            const limit = limitMessage(option.product_id, kept, quantity);
            if (limit) {
                showInfo(limit);
                return;
            }
            if (hasRoomFor(quantity - choices.filter(choice => !index.zones.has(choice.seat_uid)).length)) {
                setPreferredPriceId(option.price_id);
                bestAvailableMutation.mutate({option, quantity});
            }
        },
        changeOption: (line: BasketLine, option: TicketOption) => {
            const others = choices.filter((choice, position) => lineKey(choice, position) !== line.key);
            const limit = limitMessage(option.product_id, others, 1);
            if (limit) {
                showInfo(limit);
                return;
            }
            setPreferredPriceId(option.price_id);
            onChange(choices.map((choice, position) =>
                lineKey(choice, position) === line.key
                    ? {...choice, product_id: option.product_id, price_id: option.price_id}
                    : choice));
        },
        removeLine: (line: BasketLine) => onChange(choices.filter((choice, position) => lineKey(choice, position) !== line.key)),
    };
};

export type SeatSelection = ReturnType<typeof useSeatSelection>;
