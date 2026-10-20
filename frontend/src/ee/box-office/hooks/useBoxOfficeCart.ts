import {useMemo, useReducer} from "react";
import {
    BoxOfficeOrderItemRequest,
    BoxOfficeProduct,
    BoxOfficeProductPrice,
    TaxAndFee,
    TaxAndFeeCalculationType,
    TaxAndFeeType,
} from "../../../types.ts";

export interface CartLine {
    productId: number;
    priceId: number;
    quantity: number;
    overridePrice?: number;
    seatUids?: string[];
}

export interface CartDiscount {
    type: 'FIXED' | 'PERCENTAGE';
    value: number;
}

export interface CartBuyer {
    first_name: string;
    last_name: string;
    email: string;
}

interface CartState {
    lines: Record<number, CartLine>;
    discount: CartDiscount | null;
    buyer: CartBuyer;
    questionAnswers: { question_id: number; response: any }[];
    attendeeAnswers: Record<string, { question_id: number; response: any }[]>;
    idempotencyKey: string;
    serverErrors: { cart?: string; lines: Record<number, string> };
}

type CartAction =
    | { type: 'increment'; product: BoxOfficeProduct; price: BoxOfficeProductPrice; isSeated: boolean }
    | { type: 'decrement'; priceId: number }
    | { type: 'assignSeats'; assignments: SeatAssignment[] }
    | { type: 'removeSeat'; seatUid: string }
    | { type: 'dropSeats'; seatUids: string[] }
    | { type: 'setOverride'; priceId: number; overridePrice: number | undefined }
    | { type: 'setDiscount'; discount: CartDiscount | null }
    | { type: 'setBuyer'; buyer: CartBuyer }
    | { type: 'setQuestionAnswers'; answers: { question_id: number; response: any }[] }
    | { type: 'setAttendeeAnswers'; answers: Record<string, { question_id: number; response: any }[]> }
    | { type: 'applyServerErrors'; cart?: string; lines: Record<number, string> }
    | { type: 'clearServerErrors' }
    | { type: 'renewKey' }
    | { type: 'reset' };

const newKey = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto)
    ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0;
        return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });

const emptyBuyer: CartBuyer = {first_name: '', last_name: '', email: ''};

const initialState = (): CartState => ({
    lines: {},
    discount: null,
    buyer: emptyBuyer,
    questionAnswers: [],
    attendeeAnswers: {},
    idempotencyKey: newKey(),
    serverErrors: {lines: {}},
});

export interface SeatAssignment {
    product: BoxOfficeProduct;
    price: BoxOfficeProductPrice;
    seatUids: string[];
}

export const lineCap = (product: BoxOfficeProduct, price: BoxOfficeProductPrice, isSeated = false): number => {
    const remaining = isSeated ? Infinity : price.quantity_remaining ?? Infinity;
    const perOrder = product.max_per_order || 100;
    return Math.min(remaining, perOrder);
};

export const openSeatCount = (line: CartLine): number => Math.max(0, line.quantity - (line.seatUids?.length ?? 0));

export const seatPrice = (price: BoxOfficeProductPrice, bandKey: string | undefined): number =>
    (bandKey === undefined ? undefined : price.band_prices?.[bandKey]) ?? price.price_including_taxes_and_fees;

interface CartOrderItem {
    line: CartLine;
    item: BoxOfficeOrderItemRequest;
}

export const orderItemsFor = (lines: CartLine[], bandOfSeat?: (seatUid: string) => string | undefined): CartOrderItem[] =>
    lines.flatMap(line => {
        const item: BoxOfficeOrderItemRequest = {
            product_id: line.productId,
            product_price_id: line.priceId,
            quantity: line.quantity,
            override_price: line.overridePrice,
            seat_uids: line.seatUids,
        };
        if (!line.seatUids?.length || openSeatCount(line) > 0) {
            return [{line, item}];
        }
        const seatsByBand = new Map<string | undefined, string[]>();
        line.seatUids.forEach(uid => {
            const bandKey = bandOfSeat?.(uid);
            seatsByBand.set(bandKey, [...(seatsByBand.get(bandKey) ?? []), uid]);
        });
        return [...seatsByBand.values()].map(seatUids => ({
            line,
            item: {...item, quantity: seatUids.length, seat_uids: seatUids},
        }));
    });

const taxOrFeeAmount = (taxOrFee: TaxAndFee, amount: number): number =>
    taxOrFee.calculation_type === TaxAndFeeCalculationType.Fixed
        ? taxOrFee.rate ?? 0
        : amount * (taxOrFee.rate ?? 0) / 100;

export const priceWithTaxesAndFees = (price: number, taxesAndFees: TaxAndFee[] | undefined): number => {
    if (price <= 0) {
        return 0;
    }
    const all = taxesAndFees ?? [];
    const fees = all
        .filter(taxOrFee => taxOrFee.type === TaxAndFeeType.Fee)
        .reduce((sum, fee) => sum + taxOrFeeAmount(fee, price), 0);
    const taxes = all
        .filter(taxOrFee => taxOrFee.type === TaxAndFeeType.Tax)
        .reduce((sum, tax) => sum + taxOrFeeAmount(tax, price + fees), 0);
    return price + fees + taxes;
};

const lineSubtotal = (
    line: CartLine,
    product: BoxOfficeProduct,
    price: BoxOfficeProductPrice,
    bandOfSeat?: (seatUid: string) => string | undefined,
): number => {
    if (line.overridePrice !== undefined) {
        return priceWithTaxesAndFees(line.overridePrice, product.taxes) * line.quantity;
    }
    const seated = (line.seatUids ?? []).reduce((sum, uid) => sum + seatPrice(price, bandOfSeat?.(uid)), 0);
    return seated + openSeatCount(line) * price.price_including_taxes_and_fees;
};

const lineSubtotalBeforeTaxesAndFees = (line: CartLine, price: BoxOfficeProductPrice, subtotal: number): number => {
    if (line.overridePrice !== undefined) {
        return line.overridePrice * line.quantity;
    }
    return price.price_including_taxes_and_fees > 0
        ? subtotal * price.price / price.price_including_taxes_and_fees
        : 0;
};

interface PricedLine {
    subtotal: number;
    subtotalBeforeTaxesAndFees: number;
    hasOverride: boolean;
}

const discountAmountFor = (pricedLines: PricedLine[], discount: CartDiscount | null): number => {
    if (!discount) {
        return 0;
    }
    if (discount.type === 'PERCENTAGE') {
        return pricedLines
            .filter(pricedLine => !pricedLine.hasOverride)
            .reduce((sum, pricedLine) => sum + pricedLine.subtotal, 0) * Math.min(discount.value, 100) / 100;
    }
    const subtotal = pricedLines.reduce((sum, pricedLine) => sum + pricedLine.subtotal, 0);
    const beforeTaxesAndFees = pricedLines.reduce((sum, pricedLine) => sum + pricedLine.subtotalBeforeTaxesAndFees, 0);
    return beforeTaxesAndFees > 0
        ? subtotal * Math.min(discount.value, beforeTaxesAndFees) / beforeTaxesAndFees
        : 0;
};

const withoutSeats = (state: CartState, isDropped: (seatUid: string) => boolean): CartState => {
    const lines = Object.fromEntries(Object.entries(state.lines).map(([priceId, line]) => [
        priceId,
        line.seatUids ? {...line, seatUids: line.seatUids.filter(uid => !isDropped(uid))} : line,
    ]));
    return {...state, lines, idempotencyKey: newKey(), serverErrors: {lines: {}}};
};

const reducer = (state: CartState, action: CartAction): CartState => {
    switch (action.type) {
        case 'increment': {
            const existing = state.lines[action.price.id];
            const cap = lineCap(action.product, action.price, action.isSeated);
            const nextQuantity = Math.min((existing?.quantity ?? 0) + 1, cap);
            if (nextQuantity === (existing?.quantity ?? 0)) return state;
            return {
                ...state,
                idempotencyKey: newKey(),
                serverErrors: {lines: {}},
                lines: {
                    ...state.lines,
                    [action.price.id]: {
                        productId: action.product.id,
                        priceId: action.price.id,
                        quantity: nextQuantity,
                        overridePrice: existing?.overridePrice,
                        seatUids: action.isSeated ? existing?.seatUids ?? [] : undefined,
                    },
                },
            };
        }
        case 'decrement': {
            const existing = state.lines[action.priceId];
            if (!existing) return state;
            const lines = {...state.lines};
            if (existing.quantity <= 1) {
                delete lines[action.priceId];
            } else {
                lines[action.priceId] = {...existing, quantity: existing.quantity - 1, seatUids: existing.seatUids?.slice(0, existing.quantity - 1)};
            }
            return {...state, lines, idempotencyKey: newKey(), serverErrors: {lines: {}}};
        }
        case 'assignSeats': {
            const lines = {...state.lines};
            action.assignments.forEach(({product, price, seatUids}) => {
                const existing = lines[price.id];
                const assigned = [...(existing?.seatUids ?? []), ...seatUids];
                lines[price.id] = {
                    productId: product.id,
                    priceId: price.id,
                    quantity: Math.max(existing?.quantity ?? 0, assigned.length),
                    overridePrice: existing?.overridePrice,
                    seatUids: assigned,
                };
            });
            return {...state, lines, idempotencyKey: newKey(), serverErrors: {lines: {}}};
        }
        case 'removeSeat': {
            let removed = false;
            return withoutSeats(state, uid => {
                if (removed || uid !== action.seatUid) return false;
                removed = true;
                return true;
            });
        }
        case 'dropSeats':
            return withoutSeats(state, uid => action.seatUids.includes(uid));
        case 'setOverride': {
            const existing = state.lines[action.priceId];
            if (!existing) return state;
            return {
                ...state,
                idempotencyKey: newKey(),
                lines: {...state.lines, [action.priceId]: {...existing, overridePrice: action.overridePrice}},
            };
        }
        case 'setDiscount':
            return {...state, discount: action.discount, idempotencyKey: newKey()};
        case 'setBuyer':
            return {...state, buyer: action.buyer, idempotencyKey: newKey()};
        case 'setQuestionAnswers':
            return {...state, questionAnswers: action.answers, idempotencyKey: newKey()};
        case 'setAttendeeAnswers':
            return {...state, attendeeAnswers: action.answers, idempotencyKey: newKey()};
        case 'applyServerErrors':
            return {...state, serverErrors: {cart: action.cart, lines: action.lines}};
        case 'clearServerErrors':
            return {...state, serverErrors: {lines: {}}};
        case 'renewKey':
            return {...state, idempotencyKey: newKey()};
        case 'reset':
            return initialState();
    }
};

export const useBoxOfficeCart = (
    products: BoxOfficeProduct[] | undefined,
    bandOfSeat?: (seatUid: string) => string | undefined,
) => {
    const [state, dispatch] = useReducer(reducer, undefined, initialState);

    const priceLookup = useMemo(() => {
        const map = new Map<number, { product: BoxOfficeProduct; price: BoxOfficeProductPrice }>();
        products?.forEach(product => product.prices.forEach(price => map.set(price.id, {product, price})));
        return map;
    }, [products]);

    const lines = useMemo(() => Object.values(state.lines), [state.lines]);

    const lineTotals = useMemo(() => new Map(lines.map(line => {
        const entry = priceLookup.get(line.priceId);
        return [line.priceId, entry ? lineSubtotal(line, entry.product, entry.price, bandOfSeat) : 0];
    })), [lines, priceLookup, bandOfSeat]);

    const pricedLines = useMemo(() => lines.map((line): PricedLine => {
        const entry = priceLookup.get(line.priceId);
        const subtotal = lineTotals.get(line.priceId) ?? 0;
        return {
            subtotal,
            subtotalBeforeTaxesAndFees: entry ? lineSubtotalBeforeTaxesAndFees(line, entry.price, subtotal) : 0,
            hasOverride: line.overridePrice !== undefined,
        };
    }), [lines, priceLookup, lineTotals]);

    const totals = useMemo(() => {
        const subtotal = pricedLines.reduce((sum, pricedLine) => sum + pricedLine.subtotal, 0);
        const itemCount = lines.reduce((sum, line) => sum + line.quantity, 0);
        const discountAmount = discountAmountFor(pricedLines, state.discount);
        return {subtotal, discountAmount, total: Math.max(0, subtotal - discountAmount), itemCount};
    }, [lines, pricedLines, state.discount]);

    return {
        state,
        lines,
        priceLookup,
        lineTotals,
        totals,
        totalWithDiscount: (discount: CartDiscount) => Math.max(0, totals.subtotal - discountAmountFor(pricedLines, discount)),
        increment: (product: BoxOfficeProduct, price: BoxOfficeProductPrice, isSeated = false) => dispatch({type: 'increment', product, price, isSeated}),
        decrement: (priceId: number) => dispatch({type: 'decrement', priceId}),
        assignSeats: (assignments: SeatAssignment[]) => dispatch({type: 'assignSeats', assignments}),
        removeSeat: (seatUid: string) => dispatch({type: 'removeSeat', seatUid}),
        dropSeats: (seatUids: string[]) => dispatch({type: 'dropSeats', seatUids}),
        setOverride: (priceId: number, overridePrice: number | undefined) => dispatch({type: 'setOverride', priceId, overridePrice}),
        setDiscount: (discount: CartDiscount | null) => dispatch({type: 'setDiscount', discount}),
        setBuyer: (buyer: CartBuyer) => dispatch({type: 'setBuyer', buyer}),
        setQuestionAnswers: (answers: { question_id: number; response: any }[]) => dispatch({type: 'setQuestionAnswers', answers}),
        setAttendeeAnswers: (answers: Record<string, { question_id: number; response: any }[]>) => dispatch({type: 'setAttendeeAnswers', answers}),
        applyServerErrors: (cart: string | undefined, lineErrors: Record<number, string>) => dispatch({type: 'applyServerErrors', cart, lines: lineErrors}),
        clearServerErrors: () => dispatch({type: 'clearServerErrors'}),
        renewKey: () => dispatch({type: 'renewKey'}),
        reset: () => dispatch({type: 'reset'}),
    };
};

export type BoxOfficeCart = ReturnType<typeof useBoxOfficeCart>;
