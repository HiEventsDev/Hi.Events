import {t} from "@lingui/macro";
import dayjs from "dayjs";
import {Product, TaxAndFee, TaxAndFeeCalculationType} from "../../../types.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {getSafeLocale} from "../../../utilites/dates.ts";
import {getClientLocale} from "../../../locales.ts";
import {localeFormats} from "../../../utilites/dateLocales.ts";

export interface RowSummary {
    text: string;
    emphasized: boolean;
}

const hasValue = (value: unknown): boolean => value !== undefined && value !== null && value !== '';

export const formatLocalDateTime = (value: string | Date): string => {
    const locale = getSafeLocale(getClientLocale());

    return dayjs(value).locale(locale).format(localeFormats[locale].dayMonthTime);
};

export const descriptionSummary = (values: Product): RowSummary => {
    const plainText = (values.description || '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (!plainText) {
        return {text: t`None yet`, emphasized: false};
    }

    return {
        text: plainText.length > 40 ? plainText.slice(0, 40) + '…' : plainText,
        emphasized: true,
    };
};

export const saleWindowSummary = (values: Product): RowSummary => {
    const start = hasValue(values.sale_start_date) ? formatLocalDateTime(values.sale_start_date as string) : undefined;
    const end = hasValue(values.sale_end_date) ? formatLocalDateTime(values.sale_end_date as string) : undefined;

    if (start && end) {
        return {text: `${start} → ${end}`, emphasized: true};
    }
    if (start) {
        return {text: t`From ${start}`, emphasized: true};
    }
    if (end) {
        return {text: t`Until ${end}`, emphasized: true};
    }

    return {
        text: t`Always on sale`,
        emphasized: !!values.hide_before_sale_start_date || !!values.hide_after_sale_end_date,
    };
};

export const eventPageSummary = (values: Product): RowSummary => {
    const parts = [
        values.show_quantity_remaining && t`Shows remaining`,
        values.hide_when_sold_out && t`Hides sold out`,
        values.start_collapsed && t`Starts collapsed`,
    ].filter(Boolean) as string[];

    if (parts.length === 0) {
        return {text: t`Defaults`, emphasized: false};
    }

    return {text: parts.join(' · '), emphasized: true};
};

export const waitlistSummary = (values: Product): RowSummary => {
    if (values.waitlist_enabled) {
        return {text: t`On`, emphasized: true};
    }

    return {text: t`Off`, emphasized: false};
};

export const taxAndFeeLabel = (item: TaxAndFee, currency?: string): string => {
    return item.name + ' - ' + (item.calculation_type === TaxAndFeeCalculationType.Percentage
        ? item.rate + '%'
        : formatCurrency(Number(item.rate), currency || 'USD'));
};

export const taxesSummary = (values: Product, taxesAndFees?: TaxAndFee[], currency?: string): RowSummary => {
    const selected = (taxesAndFees || []).filter(
        (item) => (values.tax_and_fee_ids || []).map(String).includes(String(item.id)),
    );

    if (selected.length === 0) {
        return {text: t`None`, emphasized: false};
    }

    const first = taxAndFeeLabel(selected[0], currency);

    if (selected.length === 1) {
        return {text: first, emphasized: true};
    }

    const additionalCount = selected.length - 1;

    return {text: t`${first} + ${additionalCount} more`, emphasized: true};
};

export const orderLimitsSummary = (values: Product): RowSummary => {
    const min = hasValue(values.min_per_order) ? Number(values.min_per_order) : undefined;
    const max = hasValue(values.max_per_order) ? Number(values.max_per_order) : undefined;
    const emphasized = (min !== undefined && min !== 1) || (max !== undefined && max !== 100);

    if (min !== undefined && max !== undefined) {
        return {text: t`${min}–${max} per order`, emphasized};
    }
    if (min !== undefined) {
        return {text: t`Min ${min} per order`, emphasized};
    }
    if (max !== undefined) {
        return {text: t`Up to ${max} per order`, emphasized};
    }

    return {text: t`No limits`, emphasized: false};
};

export const addonsSummary = (values: Product): RowSummary => {
    const count = values.addon_product_ids?.length || 0;
    const parts = [
        count === 1 ? t`1 add-on` : count > 1 ? t`${count} add-ons` : undefined,
        values.is_addon_only ? t`Add-on only` : undefined,
    ].filter(Boolean) as string[];

    if (parts.length === 0) {
        return {text: t`None`, emphasized: false};
    }

    return {text: parts.join(' · '), emphasized: true};
};

export const highlightSummary = (values: Product): RowSummary => {
    if (!values.is_highlighted) {
        return {text: t`Off`, emphasized: false};
    }

    return {
        text: values.highlight_message ? `“${values.highlight_message}”` : t`Highlighted`,
        emphasized: true,
    };
};

export const accessSummary = (values: Product): RowSummary => {
    if (values.is_hidden) {
        return {text: t`Hidden from everyone`, emphasized: true};
    }
    if (values.is_hidden_without_promo_code) {
        return {text: t`Promo code required`, emphasized: true};
    }

    return {text: t`Public`, emphasized: false};
};
