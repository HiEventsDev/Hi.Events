import {Product, ProductPrice, TaxAndFee, TaxAndFeeCalculationType, TaxAndFeeType} from "../../../../types.ts";

type PriceDisplayMode = 'INCLUSIVE' | 'EXCLUSIVE' | undefined;

interface UnitTaxesAndFees {
    tax: number;
    fee: number;
}

const toCents = (amount: number): number => {
    const magnitude = Math.abs(amount);
    let cents = Math.floor(magnitude * 100);

    if ((cents + 1) / 100 === magnitude) {
        cents += 1;
    }

    if (magnitude >= (cents + 0.5) / 100) {
        cents += 1;
    }

    return Math.sign(amount) * cents;
};

const roundHalfUp = (amount: number): number => toCents(amount) / 100;

const chargeOn = (taxOrFee: TaxAndFee, base: number): number => {
    if (base === 0) {
        return 0;
    }

    const rate = Number(taxOrFee.rate ?? 0);

    if (taxOrFee.calculation_type === TaxAndFeeCalculationType.Fixed) {
        return rate;
    }

    return taxOrFee.calculation_type === TaxAndFeeCalculationType.Percentage ? (base * rate) / 100 : NaN;
};

const unitTaxesAndFees = (product: Product, unitPrice: number): UnitTaxesAndFees => {
    const taxesAndFees = product.taxes ?? [];
    const fee = taxesAndFees
        .filter(taxOrFee => taxOrFee.type === TaxAndFeeType.Fee)
        .reduce((total, taxOrFee) => total + chargeOn(taxOrFee, unitPrice), 0);
    const tax = taxesAndFees
        .filter(taxOrFee => taxOrFee.type === TaxAndFeeType.Tax)
        .reduce((total, taxOrFee) => total + chargeOn(taxOrFee, unitPrice + fee), 0);

    return {tax, fee};
};

const matchesPublishedTotals = (price: ProductPrice, unit: UnitTaxesAndFees): boolean =>
    toCents(unit.tax) === toCents(price.tax_total ?? 0) && toCents(unit.fee) === toCents(price.fee_total ?? 0);

export const getLineTotal = (
    product: Product,
    price: ProductPrice,
    quantity: number,
    priceDisplayMode: PriceDisplayMode,
): number => {
    const unitPrice = Number(price.price);

    if (priceDisplayMode !== 'INCLUSIVE') {
        return roundHalfUp(unitPrice * quantity);
    }

    const unit = unitTaxesAndFees(product, unitPrice);

    if (!matchesPublishedTotals(price, unit)) {
        return roundHalfUp(quantity * (unitPrice + (price.tax_total ?? 0) + (price.fee_total ?? 0)));
    }

    return roundHalfUp(unitPrice * quantity + unit.tax * quantity + unit.fee * quantity);
};

export const getBeforeDiscountDisplayPrice = (
    price: ProductPrice,
    priceDisplayMode: PriceDisplayMode,
): number | null => {
    if (!price.is_discounted || !price.price_before_discount) {
        return null;
    }

    if (priceDisplayMode !== 'INCLUSIVE') {
        return Number(price.price_before_discount);
    }

    return price.price_before_discount_including_taxes_and_fees != null
        ? Number(price.price_before_discount_including_taxes_and_fees)
        : null;
};
