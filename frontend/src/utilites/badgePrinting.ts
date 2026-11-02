import {ensureHomepageFontLoaded} from "./fontLoader.ts";
import {isSsr} from "./helpers.ts";

const ASSET_TIMEOUT_MS = 5000;

const PRINTING_BODY_CLASS = 'hi-badge-printing';

const IOS_PRINT_INSETS_MM = {top: 12.7, right: 12.7, bottom: 21, left: 12.7};

export const printsOnlyFromATap = (): boolean => {
    if (isSsr()) {
        return false;
    }
    const {userAgent, maxTouchPoints} = window.navigator;
    return /iPad|iPhone|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && maxTouchPoints > 1);
};

export type DevicePlatform = 'mac' | 'windows' | 'ipad';

export const detectDevicePlatform = (): DevicePlatform => {
    if (isSsr()) {
        return 'mac';
    }
    if (printsOnlyFromATap()) {
        return 'ipad';
    }
    return /Windows/.test(window.navigator.userAgent) ? 'windows' : 'mac';
};

export const buildChromeLaunchCommand = (
    platform: Exclude<DevicePlatform, 'ipad'>,
    pageUrl: string,
    options: { silentPrinting: boolean; kiosk: boolean },
): string => {
    const switches = [
        options.silentPrinting ? '--kiosk-printing' : null,
        options.kiosk ? '--kiosk' : null,
    ].filter(Boolean).join(' ');

    return platform === 'windows'
        // eslint-disable-next-line lingui/no-unlocalized-strings
        ? `chrome ${switches} "${pageUrl}"`
        // eslint-disable-next-line lingui/no-unlocalized-strings
        : `open -a "Google Chrome" --args ${switches} '${pageUrl}'`;
};

export const getIosPrintScale = (page: { width: number; height: number }): number => Math.min(
    1,
    (page.width - IOS_PRINT_INSETS_MM.left - IOS_PRINT_INSETS_MM.right) / page.width,
    (page.height - IOS_PRINT_INSETS_MM.top - IOS_PRINT_INSETS_MM.bottom) / page.height,
);

export const showOnlyBadgeSheetsInPrint = (): () => void => {
    document.body.classList.add(PRINTING_BODY_CLASS);
    return () => document.body.classList.remove(PRINTING_BODY_CLASS);
};

export const printWithoutPageAddress = () => {
    const pageAddress = window.location.href;
    window.history.replaceState(window.history.state, '', '/');
    try {
        window.print();
    } finally {
        window.history.replaceState(window.history.state, '', pageAddress);
    }
};

export const printBadgeSheets = (onAfterPrint?: () => void): () => void => {
    const hideRestOfPage = showOnlyBadgeSheetsInPrint();
    const handleAfterPrint = () => {
        hideRestOfPage();
        onAfterPrint?.();
    };
    window.addEventListener('afterprint', handleAfterPrint, {once: true});
    printWithoutPageAddress();

    return () => {
        window.removeEventListener('afterprint', handleAfterPrint);
        hideRestOfPage();
    };
};

const waitForImage = (image: HTMLImageElement): Promise<void> => {
    if (image.complete) {
        return image.naturalWidth > 0 && image.decode ? image.decode().catch(() => undefined) : Promise.resolve();
    }

    return new Promise((resolve) => {
        image.addEventListener('load', () => resolve(), {once: true});
        image.addEventListener('error', () => resolve(), {once: true});
    });
};

const waitForFontStylesheets = (): Promise<unknown> => Promise.all(
    Array.from(document.querySelectorAll<HTMLLinkElement>('link[id^="hi-homepage-font-"]'))
        .filter((link) => !link.sheet)
        .map((link) => new Promise<void>((resolve) => {
            link.addEventListener('load', () => resolve(), {once: true});
            link.addEventListener('error', () => resolve(), {once: true});
        })),
);

const waitForAssets = async (root: HTMLElement, fontFamily: string): Promise<void> => {
    await waitForFontStylesheets();
    await document.fonts?.load(`800 32px '${fontFamily}'`).catch(() => undefined);
    await document.fonts?.ready;
    await Promise.all(Array.from(root.querySelectorAll('img')).map(waitForImage));
};

const nextFrame = () => new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

export const waitForBadgesToRender = async (root: HTMLElement | null, fontFamily: string): Promise<void> => {
    if (isSsr() || !root) {
        return;
    }

    ensureHomepageFontLoaded(fontFamily);
    await Promise.race([
        waitForAssets(root, fontFamily),
        new Promise<void>((resolve) => setTimeout(resolve, ASSET_TIMEOUT_MS)),
    ]);
    await nextFrame();
    await nextFrame();
};
