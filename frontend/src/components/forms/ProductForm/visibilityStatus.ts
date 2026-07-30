import {t} from "@lingui/macro";
import {Product} from "../../../types.ts";
import {formatLocalDateTime} from "./ledgerSummaries.ts";

export type VisibilityLevel = 'visible' | 'conditional' | 'hidden';

export interface VisibilityStatus {
    level: VisibilityLevel;
    message: string;
}

const hasValue = (value: unknown): boolean => value !== undefined && value !== null && value !== '';

export const computeVisibilityStatus = (values: Product, nowInEventTz: string): VisibilityStatus => {
    if (values.is_hidden) {
        return {
            level: 'hidden',
            message: t`Hidden from everyone — the master hide switch is on.`,
        };
    }

    if (values.is_hidden_without_promo_code) {
        return {
            level: 'conditional',
            message: t`Only visible to buyers with an applicable promo code.`,
        };
    }

    if (values.hide_before_sale_start_date && hasValue(values.sale_start_date) && String(values.sale_start_date) > nowInEventTz) {
        const start = formatLocalDateTime(values.sale_start_date as string);

        return {
            level: 'conditional',
            message: t`Visible from ${start} — hidden until sales open.`,
        };
    }

    if (values.hide_after_sale_end_date && hasValue(values.sale_end_date) && String(values.sale_end_date) < nowInEventTz) {
        const end = formatLocalDateTime(values.sale_end_date as string);

        return {
            level: 'conditional',
            message: t`Hidden — sales ended ${end}.`,
        };
    }

    return {
        level: 'visible',
        message: t`Visible to everyone on the event page.`,
    };
};
