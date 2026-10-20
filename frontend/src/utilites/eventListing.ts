import dayjs, {Dayjs} from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {
    Event,
    EventLifecycleStatus,
    EventOccurrence,
    EventOccurrenceStatus,
    EventType,
    Product,
    ProductPrice,
    ProductPriceType,
    ProductType,
} from "../types.ts";
import {getDisplayPrice} from "../components/common/Currency";
import {getProductsFromEvent} from "./helpers.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

export type EventPriceSummary =
    | {kind: 'soldOut'}
    | {kind: 'free'}
    | {kind: 'donation'}
    | {kind: 'exact'; amount: number}
    | {kind: 'from'; amount: number};

export interface EventScheduleSummary {
    start: string | null;
    end: string | null;
    hasEnded: boolean;
    isSeriesRange: boolean;
    isHappeningNow: boolean;
    additionalDateCount: number;
}

const isPurchasablePrice = (price: ProductPrice): boolean =>
    !price.is_sold_out
    && !price.is_after_sale_end_date
    && !price.is_locked_behind_earlier_tier
    && !price.is_hidden;

const isOnSale = (product: Product): boolean =>
    !product.is_sold_out && !product.is_after_sale_end_date;

const bandPricesOf = (price: ProductPrice): number[] => Object.values(price.band_prices ?? {});

const priceAmounts = (price: ProductPrice, priceDisplayMode?: string): number[] => {
    const bandPrices = bandPricesOf(price);
    return bandPrices.length > 0 ? bandPrices : [getDisplayPrice(price, priceDisplayMode)];
};

export const summariseEventPrice = (event: Event): EventPriceSummary | null => {
    if (event.products_sold_out) {
        return {kind: 'soldOut'};
    }

    const products = (getProductsFromEvent(event) ?? []).filter(product => !product.is_addon_only);
    const tickets = products.filter(product => product.product_type === ProductType.Ticket);
    const listed = tickets.length > 0 ? tickets : products;

    if (listed.length === 0) {
        return null;
    }

    const upcomingOccurrences = (event.occurrences ?? []).filter(occurrence => !occurrence.is_past);
    const everyUpcomingDateSoldOut = event.type === EventType.RECURRING
        && upcomingOccurrences.length > 0
        && upcomingOccurrences.every(occurrence => occurrence.status === EventOccurrenceStatus.SOLD_OUT);

    if (event.upcoming_occurrences_sold_out || everyUpcomingDateSoldOut || listed.every(product => product.is_sold_out)) {
        return {kind: 'soldOut'};
    }

    const onSale = listed.filter(isOnSale);
    if (onSale.length === 0) {
        return null;
    }

    const fixedPriceProducts = onSale.filter(product => product.type !== ProductPriceType.Donation);
    if (fixedPriceProducts.length === 0) {
        return {kind: 'donation'};
    }

    const purchasablePrices = fixedPriceProducts.flatMap(product => (product.prices ?? []).filter(isPurchasablePrice));
    const hasBandPrices = purchasablePrices.some(price => bandPricesOf(price).length > 0);
    const priceDisplayMode = hasBandPrices ? 'INCLUSIVE' : event.settings?.price_display_mode;
    const amounts = purchasablePrices.flatMap(price => priceAmounts(price, priceDisplayMode));

    if (amounts.length === 0) {
        return null;
    }

    const lowest = Math.min(...amounts);
    const highest = Math.max(...amounts);

    if (highest === 0) {
        return {kind: 'free'};
    }

    return lowest === highest ? {kind: 'exact', amount: lowest} : {kind: 'from', amount: lowest};
};

const startsAt = (occurrence: EventOccurrence): number => dayjs.utc(occurrence.start_date).valueOf();

const isInProgress = (start: string | null | undefined, end: string | null | undefined, now: Dayjs): boolean => {
    if (!start || !end) {
        return false;
    }
    const startMoment = dayjs.utc(start);
    const endMoment = dayjs.utc(end);

    return endMoment.isAfter(startMoment) && !now.isBefore(startMoment) && now.isBefore(endMoment);
};

const distinctEnd = (start: string | null | undefined, end: string | null | undefined): string | null =>
    start && end && dayjs.utc(end).isAfter(dayjs.utc(start)) ? end : null;

export const summariseEventSchedule = (event: Event, now: Dayjs = dayjs()): EventScheduleSummary => {
    const hasEnded = event.lifecycle_status === EventLifecycleStatus.ENDED;
    const isRecurring = event.type === EventType.RECURRING;

    if (!isRecurring || hasEnded) {
        return {
            start: event.start_date ?? null,
            end: distinctEnd(event.start_date, event.end_date),
            hasEnded,
            isSeriesRange: isRecurring,
            isHappeningNow: !hasEnded && isInProgress(event.start_date, event.end_date, now),
            additionalDateCount: 0,
        };
    }

    const upcoming = (event.occurrences ?? [])
        .filter(occurrence => !occurrence.is_past)
        .sort((a, b) => startsAt(a) - startsAt(b));
    const nextBookableAt = event.next_occurrence_start_date
        ? dayjs.utc(event.next_occurrence_start_date).valueOf()
        : null;
    const next = upcoming.find(occurrence => startsAt(occurrence) === nextBookableAt)
        ?? upcoming.find(occurrence => !now.isAfter(dayjs.utc(occurrence.start_date)))
        ?? upcoming[0];

    const start = next?.start_date ?? event.next_occurrence_start_date ?? event.start_date ?? null;
    const dateKey = (date: string) => dayjs.utc(date).tz(event.timezone).format('YYYY-MM-DD');
    const otherDates = new Set(upcoming.map(occurrence => dateKey(occurrence.start_date)));
    if (start) {
        otherDates.delete(dateKey(start));
    }

    return {
        start,
        end: next ? distinctEnd(next.start_date, next.end_date) : null,
        hasEnded,
        isSeriesRange: false,
        isHappeningNow: upcoming.some(occurrence => isInProgress(occurrence.start_date, occurrence.end_date, now)),
        additionalDateCount: otherDates.size,
    };
};
