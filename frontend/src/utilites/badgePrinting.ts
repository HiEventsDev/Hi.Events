import {ensureHomepageFontLoaded} from "./fontLoader.ts";
import {isSsr} from "./helpers.ts";

const ASSET_TIMEOUT_MS = 5000;

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
