import {t} from "@lingui/macro";
import {
    Event,
    EventSettings,
    EventType,
    Product,
    ProductPrice,
    ProductPriceType,
    ProductType,
    TaxAndFee,
    TaxAndFeeCalculationType,
    TaxAndFeeType,
} from "../../../types.ts";
import {Constants} from "../../../constants.ts";

const sumForType = (taxesAndFees: TaxAndFee[], type: TaxAndFeeType, basePrice: number): number => {
    return taxesAndFees
        .filter((item) => item.type === type)
        .reduce((total, item) => {
            if (item.calculation_type === TaxAndFeeCalculationType.Percentage) {
                return total + (basePrice * Number(item.rate || 0)) / 100;
            }
            return total + Number(item.rate || 0);
        }, 0);
};

const buildPreviewPrice = (
    id: number,
    price: number,
    selectedTaxesAndFees: TaxAndFee[],
    overrides: Partial<ProductPrice> = {},
): ProductPrice => ({
    id,
    price,
    is_available: true,
    is_sold_out: false,
    quantity_remaining: 100,
    tax_total: Number(sumForType(selectedTaxesAndFees, TaxAndFeeType.Tax, price).toFixed(2)),
    fee_total: Number(sumForType(selectedTaxesAndFees, TaxAndFeeType.Fee, price).toFixed(2)),
    ...overrides,
});

const buildPreviewPrices = (
    values: Product,
    selectedTaxesAndFees: TaxAndFee[],
    nowInEventTz: string,
): ProductPrice[] => {
    if (values.type === ProductPriceType.Tiered) {
        const tiers = values.prices || [];
        const visibleTiers = tiers.filter((tier) => !tier.is_hidden);

        return (visibleTiers.length > 0 ? visibleTiers : tiers).map((tier, index) => {
            const isBeforeSaleStart = !!tier.sale_start_date && String(tier.sale_start_date) > nowInEventTz;
            const isAfterSaleEnd = !!tier.sale_end_date && String(tier.sale_end_date) < nowInEventTz;

            return buildPreviewPrice(index + 1, Number(tier.price || 0), selectedTaxesAndFees, {
                label: tier.label || t`Tier ${index + 1}`,
                is_available: !isBeforeSaleStart && !isAfterSaleEnd,
                is_before_sale_start_date: isBeforeSaleStart,
                is_after_sale_end_date: isAfterSaleEnd,
            });
        });
    }

    const basePrice = values.type === ProductPriceType.Free ? 0 : Number(values.prices?.[0]?.price || 0);

    return [buildPreviewPrice(1, basePrice, selectedTaxesAndFees)];
};

const previewQuantityAvailable = (values: Product): number | undefined => {
    if (!values.show_quantity_remaining) {
        return undefined;
    }

    const quantities = (values.type === ProductPriceType.Tiered ? values.prices || [] : [values.prices?.[0]])
        .map((price) => price?.initial_quantity_available)
        .filter((quantity): quantity is number => quantity !== undefined && quantity !== null && String(quantity) !== '');

    if (quantities.length === 0) {
        return Constants.INFINITE_TICKETS;
    }

    return quantities.reduce((total, quantity) => total + Number(quantity), 0);
};

export const buildPreviewEvent = (
    event: Event,
    values: Product,
    nowInEventTz: string,
    taxesAndFees?: TaxAndFee[],
    settings?: EventSettings,
): Event => {
    const selectedTaxesAndFees = (taxesAndFees || []).filter(
        (item) => (values.tax_and_fee_ids || []).map(String).includes(String(item.id)),
    );

    const prices = buildPreviewPrices(values, selectedTaxesAndFees, nowInEventTz);

    const previewProduct: Product = {
        id: -1,
        title: values.title?.trim() || (values.product_type === ProductType.General ? t`Untitled product` : t`Untitled ticket`),
        description: values.description,
        type: values.type,
        product_type: values.product_type,
        price: prices[0]?.price,
        prices,
        min_per_order: values.min_per_order ? Number(values.min_per_order) : undefined,
        max_per_order: values.max_per_order ? Number(values.max_per_order) : undefined,
        start_collapsed: values.start_collapsed,
        show_quantity_remaining: values.show_quantity_remaining,
        quantity_available: previewQuantityAvailable(values),
        is_highlighted: values.is_highlighted,
        highlight_message: values.highlight_message,
        waitlist_enabled: false,
        is_available: true,
        is_sold_out: false,
        taxes: selectedTaxesAndFees,
    };

    const categoryName = event.product_categories?.find(
        (category) => String(category.id) === String(values.product_category_id),
    )?.name;

    return {
        ...event,
        type: EventType.SINGLE,
        occurrences: [],
        settings: settings || event.settings,
        product_categories: [{
            id: -1,
            name: categoryName || t`Tickets`,
            products: [previewProduct],
        }],
    };
};
