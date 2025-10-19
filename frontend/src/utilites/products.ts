import {Attendee, Product, ProductPriceType} from "../types.ts";

export const getAttendeeProductTitle = (attendee: Attendee): string => {
    const product = attendee.product as Product;
    if (product.type !== ProductPriceType.Tiered) {
        return product.title;
    }

    const productPrice = product.prices
        ?.find(price => price.id === attendee.product_price_id);

    return product.title + (productPrice?.label ? ` - ${productPrice.label}` : '');
}

export const getAttendeeProductPrice = (attendee: Attendee, product: Product): number => {
    const productPrice = product.prices
        ?.find(price => price.id === attendee.product_price_id);

    return productPrice?.price ?? 0;
}
