import {t} from "@lingui/macro";
import {Product} from "../../../../types.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {PublicSeatMapBandProducts} from "../../api/seat-map.client.ts";

export interface TicketOption {
    product_id: number;
    price_id: number;
    label: string;
    description: string | null;
    price: number;
}

export interface SeatChoice {
    seat_uid: string;
    product_id: number;
    price_id: number;
    band_key: string | null;
}

const bandPriceKey = (bandKey: string, priceId: number) => `${bandKey}:${priceId}`;

export const ticketOptionsByBand = (
    bandProducts: PublicSeatMapBandProducts[],
    products: Product[],
): Map<string, TicketOption[]> => {
    const productsById = new Map(products.map(product => [Number(product.id), product]));

    return new Map(bandProducts.map(({band_key, products: links}) => [
        band_key,
        links.flatMap(({product_id}) => {
            const product = productsById.get(product_id);
            if (!product || product.is_available === false) {
                return [];
            }
            return (product.prices ?? [])
                .filter(price => price.id !== undefined && price.is_available !== false && !price.is_sold_out)
                .map(price => ({
                    product_id,
                    price_id: Number(price.id),
                    label: price.label ? `${product.title} – ${price.label}` : product.title,
                    description: product.description || null,
                    price: price.band_prices?.[band_key] ?? Number(price.price_including_taxes_and_fees ?? price.price),
                }));
        }),
    ]));
};

export const linkedBandKeys = (bandProducts: PublicSeatMapBandProducts[]): Set<string> =>
    new Set(bandProducts.filter(band => band.products.length > 0).map(band => band.band_key));

export const bandKeysForProduct = (bandProducts: PublicSeatMapBandProducts[], productId: number): Set<string> =>
    new Set(bandProducts.filter(band => band.products.some(link => link.product_id === productId)).map(band => band.band_key));

export const seatedProductIds = (bandProducts: PublicSeatMapBandProducts[]): Set<number> =>
    new Set(bandProducts.flatMap(band => band.products.map(link => link.product_id)));

export const choicesTotal = (choices: SeatChoice[], options: Map<string, TicketOption[]>): number => {
    const priceByBandAndPrice = new Map([...options.entries()].flatMap(([bandKey, bandOptions]) =>
        bandOptions.map(option => [bandPriceKey(bandKey, option.price_id), option.price] as const)));

    return choices.reduce(
        (total, choice) => total + (priceByBandAndPrice.get(bandPriceKey(choice.band_key ?? '', choice.price_id)) ?? 0),
        0,
    );
};

export const isBandSoldOut = (options: TicketOption[], free: number | undefined): boolean =>
    options.length === 0 || free === 0;

export const priceSummary = (prices: number[], currency: string): string | null => {
    if (prices.length === 0) {
        return null;
    }
    const lowest = Math.min(...prices);
    if (lowest === Math.max(...prices)) {
        return formatPrice(lowest, currency);
    }
    const from = formatCurrency(lowest, currency);
    return t`From ${from}`;
};

export type BandUnavailableReason = 'sold_out' | 'not_yet_on_sale' | 'sales_ended';

export const bandUnavailableReason = (
    bandKey: string,
    bandProducts: PublicSeatMapBandProducts[],
    products: Product[],
): BandUnavailableReason => {
    const productIds = new Set(bandProducts.find(band => band.band_key === bandKey)?.products.map(link => link.product_id) ?? []);
    const linked = products.filter(product => productIds.has(Number(product.id)));
    const prices = linked.flatMap(product => (product.prices ?? []).map(price => ({product, price})));

    if (prices.some(({product, price}) => !product.is_sold_out && !price.is_sold_out
        && (product.is_before_sale_start_date || price.is_before_sale_start_date || price.is_locked_behind_earlier_tier))) {
        return 'not_yet_on_sale';
    }
    if (prices.length > 0 && prices.every(({product, price}) => product.is_after_sale_end_date || price.is_after_sale_end_date)) {
        return 'sales_ended';
    }
    return 'sold_out';
};

export const formatPrice = (amount: number, currency: string): string =>
    amount === 0 ? t`Free` : formatCurrency(amount, currency);
