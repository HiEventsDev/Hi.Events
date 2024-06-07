import {Event} from "../types.ts";
import {MantineColor} from "@mantine/core";
import {getConfig} from "./config.ts";

export function isNumber(value: any): value is number {
    return typeof value === 'number'
}

export const isObjectEmpty = (objectName: any) => {
    return Object.keys(objectName).length === 0
}

export const pluck = <T, K extends keyof T>(obj: T, keys: K[]): Pick<T, K> => {
    const ret: any = {};
    for (const key of keys) {
        ret[key] = obj[key];
    }
    return ret;
};

export const getInitials = (fullName: string) => {
    const allNames = fullName.trim().split(' ');
    return allNames.reduce((acc, curr, index) => {
        if (index === 0 || index === allNames.length - 1) {
            acc = `${acc}${curr.charAt(0).toUpperCase()}`;
        }
        return acc;
    }, '');
};

export const getTicketFromEvent = (ticketId: number, event?: Event) => {
    return event?.tickets?.find(ticket => ticket.id === ticketId)
}

export const formatStatus = (status: string) => {
    return status.replaceAll('_', ' ').toLowerCase();
}

export const addQueryStringToUrl = (key: string, value: string): void => {
    const currentUrl = new URL(window?.location.href);

    if (!currentUrl.searchParams.has(key)) {
        currentUrl.searchParams.append(key, value);
    }

    window?.history.pushState({}, '', currentUrl.toString());
};

export const removeQueryStringFromUrl = (key: string): void => {
    const currentUrl = new URL(window?.location.href);

    if (currentUrl.searchParams.has(key)) {
        currentUrl.searchParams.delete(key);
    }

    window?.history.pushState({}, '', currentUrl.toString());
}

export const getStatusColor = (status: string): MantineColor => {
    switch (status) {
        case 'AWAITING_PAYMENT':
        case 'REFUND_PENDING':
        case 'PARTIALLY_REFUNDED':
            return 'orange';
        case 'CANCELLED':
        case 'REFUND_FAILED':
        case 'REFUNDED':
        case 'PAYMENT_FAILED':
            return 'red';
        case 'COMPLETED':
            return 'teal';
        default:
            return 'teal';
    }
};

export const getUrlParam = (paramName: string) => {
    const params = new URLSearchParams(window?.location.search);
    return params.get(paramName);
};

export const formatNumber = (number: number) => {
    if (!isNumber(number)) {
        return 0;
    }

    return new Intl.NumberFormat().format(number);
}

export const isSsr = () => import.meta.env.SSR;

/**
 * (c) Hi.Events Ltd 2024
 *
 * PLEASE NOTE:
 *
 * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
 *
 * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENSE
 *
 * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
 *
 * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
 */
export const iHavePurchasedALicence = () => {
    return getConfig('VITE_I_HAVE_PURCHASED_A_LICENCE');
}

export const isHiEvents = () => {
    return getConfig('VITE_FRONTEND_URL')?.includes('hi.events');
}

export const isEmptyHtml = (content: string) => {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = content;
    const textContent = tempDiv.textContent?.trim();
    return textContent === '' || textContent === null;
};
