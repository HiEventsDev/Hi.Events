import React from 'react';
import {formatCurrency} from "../../../utilites/currency.ts";
import {Ticket, TicketPrice} from "../../../types.ts";
import {t} from "@lingui/macro";

interface CurrencyProps {
    price?: number | null | undefined;
    taxName?: string;
    taxRate?: number;
    taxAndServiceFeeDisplayType?: 'inclusive' | 'exclusive';
    currency?: string;
    serviceFeeFixed?: number;
    serviceFeePercentage?: number;
    strikeThrough?: boolean;
    className?: string;
    freeLabel?: string | null;
    showAppendedText?: boolean;
}

export const Currency: React.FC<CurrencyProps> = ({
                                                      price,
                                                      taxName = 'Tax',
                                                      taxRate,
                                                      taxAndServiceFeeDisplayType = 'exclusive',
                                                      currency = 'USD',
                                                      serviceFeeFixed,
                                                      serviceFeePercentage,
                                                      strikeThrough,
                                                      className,
                                                      freeLabel,
                                                      showAppendedText = false,
                                                  }) => {
    if (!price) {
        return freeLabel ? freeLabel : formatCurrency(0, currency);
    }

    let totalServiceFee = 0;
    if (serviceFeeFixed) {
        totalServiceFee += serviceFeeFixed;
    }

    if (serviceFeePercentage) {
        totalServiceFee += (serviceFeePercentage / 100) * price;
    }

    let taxAmount = 0;
    if (taxRate) {
        taxAmount = (taxRate / 100) * (price + totalServiceFee);
    }

    let displayPrice = price;
    let appendedText = '';

    if (taxAndServiceFeeDisplayType === 'inclusive') {
        displayPrice += totalServiceFee + taxAmount;
        appendedText = 'incl. ' + taxName + ' & Fees';
    } else if (taxAndServiceFeeDisplayType === 'exclusive') {
        appendedText = `exl. ${formatCurrency(totalServiceFee + taxAmount, currency)} ` + taxName + ' & Fees';
    }

    let formattedPrice = <>{formatCurrency(displayPrice, currency)}</>;

    if (strikeThrough) {
        formattedPrice = <s>{formattedPrice}</s>;
    }

    return (
        <span className={className}>
            {formattedPrice}{' '}
            {appendedText && showAppendedText && <span>{appendedText}</span>}
        </span>
    );
};

interface TicketPriceProps {
    ticket: Ticket;
    price: TicketPrice;
    currency?: string;
    className?: string;
    freeLabel?: string | null;
    taxAndServiceFeeDisplayType?: 'inclusive' | 'exclusive';
}

export const TicketPriceDisplay: React.FC<TicketPriceProps> = ({
                                                                   ticket,
                                                                   price,
                                                                   currency = 'USD',
                                                                   className,
                                                                   freeLabel,
                                                                   taxAndServiceFeeDisplayType = 'exclusive',
                                                               }) => {

    if (ticket.type === 'FREE') {
        return (
            <span className={className}>
            {freeLabel || t`Free`}
        </span>)
    }

    let displayPrice = price.price;
    const totalTaxAndFees = (price.tax_total || 0) + (price.fee_total || 0);

    // Order taxes and service fees for display
    const orderedFees = [...(ticket.taxes || [])].sort((a, b) => a.type.localeCompare(b.type));
    const feeDescriptions = orderedFees.map(fee => fee.name).join(', ');

    let appendedText: string;
    if (taxAndServiceFeeDisplayType === 'inclusive') {
        displayPrice += totalTaxAndFees;
        appendedText = `incl. ${feeDescriptions}`;
    } else {
        const formattedFees = formatCurrency(totalTaxAndFees, currency);
        appendedText = `excl. ${formattedFees} ${feeDescriptions}`;
    }

    const formattedPrice = formatCurrency(displayPrice, currency);

    return (
        <span className={className}>
            {formattedPrice} {appendedText && <span>({appendedText})</span>}
        </span>
    );
};
