import {buildHomepageFontUrl, DEFAULT_HOMEPAGE_FONT, getHomepageFont} from "../constants/homepageFonts.ts";
import {isSsr} from "./helpers.ts";

const LINK_ID_PREFIX = 'hi-homepage-font-';

export const ensureHomepageFontLoaded = (fontValue: string | null | undefined): void => {
    if (isSsr()) {
        return;
    }

    const font = getHomepageFont(fontValue);

    if (font.value === DEFAULT_HOMEPAGE_FONT) {
        return;
    }

    const linkId = LINK_ID_PREFIX + font.bunnyFamily;
    if (document.getElementById(linkId)) {
        return;
    }

    const link = document.createElement('link');
    link.id = linkId;
    link.rel = 'stylesheet';
    link.href = buildHomepageFontUrl(font.value);
    document.head.appendChild(link);
};
