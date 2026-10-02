import {Event, Product} from "../types.ts";
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

export const getProductsFromEvent = (event?: Event): Product[] | undefined => {
    return event?.product_categories?.flatMap(category => category.products).filter(product => product !== undefined);
}

export const getProductFromEvent = (productId: number, event?: Event) => {
    return getProductsFromEvent(event)?.find(product => product.id === productId);
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

export const prefetchOnIdle = (load: () => Promise<unknown>) => {
    if (isSsr()) return;
    const whenIdle = window.requestIdleCallback ?? ((callback: () => void) => window.setTimeout(callback, 1));
    whenIdle(() => load().catch(() => undefined));
};

export const safeSessionStorageGet = (key: string): string | null => {
    if (isSsr()) return null;
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
};

export const safeSessionStorageSet = (key: string, value: string): void => {
    if (isSsr()) return;
    try {
        window.sessionStorage.setItem(key, value);
    } catch {
        return;
    }
};

export const safeSessionStorageRemove = (key: string): void => {
    if (isSsr()) return;
    try {
        window.sessionStorage.removeItem(key);
    } catch {
        return;
    }
};

export const safeLocalStorageGet = (key: string): string | null => {
    if (isSsr()) return null;
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
};

export const safeLocalStorageSet = (key: string, value: string): void => {
    if (isSsr()) return;
    try {
        window.localStorage.setItem(key, value);
    } catch {
        return;
    }
};

export const safeLocalStorageRemove = (key: string): void => {
    if (isSsr()) return;
    try {
        window.localStorage.removeItem(key);
    } catch {
        return;
    }
};

/**
 * (c) Hi.Events Ltd 2024-present
 *
 * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
 * The full licence text is in the LICENCE file in the repository root.
 *
 * Under Section 7(b) of the AGPL, the "Powered by Hi.Events" notice must stay on all web pages
 * and emails. If you modify Hi.Events you may rephrase it, for example "Powered by [Your Company]
 * based on Hi.Events", but it must still link to https://hi.events.
 *
 * The notice must stay clearly visible and legible. Do not hide or obscure it, for example by
 * shrinking its font size, lowering its contrast, matching its colour to the background, covering
 * it or moving it off-screen.
 *
 * To remove the notice you need a commercial licence: https://hi.events/licensing
 * With a licence, hide it through your licence key or configuration rather than by editing this code.
 *
 * Commercial licences help keep Hi.Events free and open source. To keep that fair for everyone who
 * pays, we may work with a third-party compliance partner to find installations that remove or
 * obscure this notice without a licence. If you hear from us or them, it will start as a friendly
 * conversation, and you'll have 30 days to get a licence or restore the notice.
 */
export const iHavePurchasedALicence = () => {
    return getConfig('VITE_I_HAVE_PURCHASED_A_LICENCE');
}

export const isHiEvents = () => {
    return getConfig('VITE_FRONTEND_URL')?.includes('.hi.events');
}

export const htmlToText = (html: string | null | undefined): string => {
    if (!html) {
        return '';
    }
    if (typeof DOMParser === 'undefined') {
        return html.replace(/<[^>]*>/g, '');
    }
    return new DOMParser().parseFromString(html, 'text/html').body.textContent ?? '';
};

export const isEmptyHtml = (content: string) => {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = content;
    const textContent = tempDiv.textContent?.trim();
    return textContent === '' || textContent === null;
};
