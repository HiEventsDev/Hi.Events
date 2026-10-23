import {Product, ProductPrice} from "../types.ts";

const isWithinSaleWindow = (item: Product | ProductPrice): boolean =>
    !item.is_before_sale_start_date && !item.is_after_sale_end_date;

export const canJoinWaitlist = (product: Product, price?: ProductPrice): boolean =>
    !!product.waitlist_enabled && isWithinSaleWindow(product) && (!price || isWithinSaleWindow(price));
