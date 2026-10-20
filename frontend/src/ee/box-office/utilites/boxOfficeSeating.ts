import {BoxOfficeProduct, BoxOfficeProductPrice} from "../../../types.ts";
import {PublicSeatMapBandProducts} from "../../seating/api/seat-map.client.ts";
import {bandKeysForProduct} from "../../seating/components/SeatPicker/ticketOptions.ts";
import {CartLine, openSeatCount, SeatAssignment} from "../hooks/useBoxOfficeCart.ts";

export interface SeatedType {
    product: BoxOfficeProduct;
    price: BoxOfficeProductPrice;
    bandKeys: Set<string>;
}

export const seatedTypes = (products: BoxOfficeProduct[], bandProducts: PublicSeatMapBandProducts[]): SeatedType[] =>
    products.flatMap(product => {
        const bandKeys = bandKeysForProduct(bandProducts, product.id);
        return bandKeys.size > 0 ? product.prices.map(price => ({product, price, bandKeys})) : [];
    });

export const seatedTypeTitle = (type: SeatedType): string =>
    type.product.type === 'TIERED' && type.price.label ? `${type.product.title} · ${type.price.label}` : type.product.title;

export const seatsToChoose = (lines: CartLine[], typesByPrice: Map<number, SeatedType>): number =>
    lines.filter(line => typesByPrice.has(line.priceId)).reduce((total, line) => total + openSeatCount(line), 0);

export const openSeatsInBand = (lines: CartLine[], typesByPrice: Map<number, SeatedType>, bandKey: string): number =>
    lines
        .filter(line => typesByPrice.get(line.priceId)?.bandKeys.has(bandKey))
        .reduce((total, line) => total + openSeatCount(line), 0);

export const fillOpenTickets = (
    lines: CartLine[],
    typesByPrice: Map<number, SeatedType>,
    bandKey: string,
    seatUids: string[],
): {assignments: SeatAssignment[]; leftover: string[]} => {
    const assignments: SeatAssignment[] = [];
    let leftover = seatUids;

    for (const line of lines) {
        const type = typesByPrice.get(line.priceId);
        const count = Math.min(openSeatCount(line), leftover.length);
        if (!type || !type.bandKeys.has(bandKey) || count === 0) {
            continue;
        }
        assignments.push({product: type.product, price: type.price, seatUids: leftover.slice(0, count)});
        leftover = leftover.slice(count);
    }

    return {assignments, leftover};
};
