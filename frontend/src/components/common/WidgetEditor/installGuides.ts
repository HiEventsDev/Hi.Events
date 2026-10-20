import {t} from "@lingui/macro";
import {combinedSnippet, htmlScriptTag, htmlWidgetTag, reactComponent} from "./embedCode.ts";

export type InstallPlatform = 'html' | 'wordpress' | 'webflow' | 'squarespace' | 'wix' | 'shopify' | 'framer' | 'react';

/* eslint-disable lingui/no-unlocalized-strings */
const PLATFORM_NAMES = {
    wordpress: 'WordPress',
    webflow: 'Webflow',
    squarespace: 'Squarespace',
    wix: 'Wix',
    shopify: 'Shopify',
    framer: 'Framer',
    react: 'React / Next.js',
};
/* eslint-enable lingui/no-unlocalized-strings */

export type InstallCodeLayout = 'split' | 'combined' | 'react';

export interface InstallGuide {
    value: InstallPlatform;
    label: string;
    steps: string[];
    tip?: string;
    code: InstallCodeLayout;
}

export const installGuides = (): InstallGuide[] => [
    {
        value: 'html',
        label: t`Any website (HTML)`,
        steps: [],
        code: 'split',
    },
    {
        value: 'wordpress',
        label: PLATFORM_NAMES.wordpress,
        steps: [
            t`Edit the page and add a Custom HTML block where the widget should appear.`,
            t`Paste the code below, then update the page.`,
        ],
        tip: t`If the widget doesn't appear, a security plugin may be removing scripts. Allow scripts from this site in its settings.`,
        code: 'combined',
    },
    {
        value: 'webflow',
        label: PLATFORM_NAMES.webflow,
        steps: [
            t`Drag a Code Embed element onto the page where the widget should appear.`,
            t`Paste the code below, save, and publish your site.`,
        ],
        tip: t`Embedded code only runs on the published site, not in the Designer.`,
        code: 'combined',
    },
    {
        value: 'squarespace',
        label: PLATFORM_NAMES.squarespace,
        steps: [
            t`Edit the page and add a Code block where the widget should appear.`,
            t`Paste the code below and save.`,
        ],
        tip: t`Code blocks that run scripts need a Squarespace plan with custom code.`,
        code: 'combined',
    },
    {
        value: 'wix',
        label: PLATFORM_NAMES.wix,
        steps: [
            t`Click Add Elements, then Embed Code, then Embed HTML, and place it on the page.`,
            t`Choose Code, paste the code below, and click Update.`,
            t`Drag the element's edges so it's tall enough for the widget.`,
        ],
        tip: t`Wix shows embedded code inside a fixed-size box. Set Checkout opens in to New tab for the best experience.`,
        code: 'combined',
    },
    {
        value: 'shopify',
        label: PLATFORM_NAMES.shopify,
        steps: [
            t`Go to Online Store, then Themes, then Customize, and open the page.`,
            t`Add a Custom Liquid section where the widget should appear.`,
            t`Paste the code below and save.`,
        ],
        code: 'combined',
    },
    {
        value: 'framer',
        label: PLATFORM_NAMES.framer,
        steps: [
            t`Add an Embed component where the widget should appear and choose HTML.`,
            t`Paste the code below and publish.`,
        ],
        tip: t`Give the embed enough height for the widget, or set Checkout opens in to New tab.`,
        code: 'combined',
    },
    {
        value: 'react',
        label: PLATFORM_NAMES.react,
        steps: [],
        code: 'react',
    },
];

export const installCode = (platform: InstallPlatform, attributes: [string, string][], scriptUrl: string): string => {
    const layout = installGuides().find(guide => guide.value === platform)?.code ?? 'split';
    if (layout === 'react') {
        return reactComponent(attributes, scriptUrl);
    }
    return layout === 'combined'
        ? combinedSnippet(attributes, scriptUrl)
        : `${htmlScriptTag(scriptUrl)}\n\n${htmlWidgetTag(attributes)}`;
};
