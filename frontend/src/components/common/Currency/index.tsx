import React from 'react';
import {formatCurrency} from "../../../utilites/currency.ts";
import {Product, ProductPrice} from "../../../types.ts";
import {t} from "@lingui/macro";
import {Popover} from "@mantine/core";
import {IconInfoCircle} from "@tabler/icons-react";

interface CurrencyProps {
    price?: number | null | undefined;
    currency?: string;
    strikeThrough?: boolean;
    className?: string;
    freeLabel?: string | null;
}

export const Currency: React.FC<CurrencyProps> = ({
                                                      price,
                                                      currency = 'USD',
                                                      strikeThrough,
                                                      className,
                                                      freeLabel,
                                                  }) => {
    if (!price) {
        return freeLabel ? freeLabel : formatCurrency(0, currency);
    }

    let formattedPrice = <>{formatCurrency(price, currency)}</>;

    if (strikeThrough) {
        formattedPrice = <s>{formattedPrice}</s>;
    }

    return (
        <span className={className}>
            {formattedPrice}
        </span>
    );
};

interface ProductPriceProps {
    product: Product;
    price: ProductPrice;
    currency?: string;
    className?: string;
    freeLabel?: string | null;
    taxAndServiceFeeDisplayType?: 'INCLUSIVE' | 'EXCLUSIVE';
}

export const ProductPriceDisplay: React.FC<ProductPriceProps> = ({
                                                                   product,
                                                                   price,
                                                                   currency = 'USD',
                                                                   className,
                                                                   freeLabel,
                                                                   taxAndServiceFeeDisplayType = 'exclusive',
                                                               }) => {
    let displayPrice = price.price;
    const totalTaxAndFees = (price.tax_total || 0) + (price.fee_total || 0);

    // Order taxes and service fees for display
    const orderedFees = [...(product.taxes || [])].sort((a, b) => a.type.localeCompare(b.type));
    const feeDescriptions = orderedFees.map(fee => fee.name).join(', ');

    const getTextAppendage = () => {
        if (taxAndServiceFeeDisplayType === 'INCLUSIVE') {
            displayPrice += totalTaxAndFees;
            return `incl. ${feeDescriptions}`;
        } else {
            const formattedFees = formatCurrency(totalTaxAndFees, currency);
            return `excl. ${formattedFees} ${feeDescriptions}`;
        }
    };

    const appendedText = totalTaxAndFees === 0 ? '' : (
        <>
            <Popover>
                <Popover.Target>
                    <span style={{cursor: 'pointer'}}> <IconInfoCircle size={18}/> </span>
                </Popover.Target>
                <Popover.Dropdown>
                    {getTextAppendage()}
                </Popover.Dropdown>
            </Popover>
        </>
    )

    const formattedPrice = formatCurrency(displayPrice, currency);

    if (displayPrice === 0 && totalTaxAndFees === 0) {
        return <span className={className}>{freeLabel || t`Free`}</span>;
    }

    return (
        <div className={className}>
            <div>{formattedPrice}</div>
            <div>{appendedText}</div>
        </div>
    );
};
