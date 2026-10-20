/* eslint-disable lingui/no-unlocalized-strings */
import {settingAttributeName, WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";

export type WidgetTarget = { eventId: string } | { organizerId: string };

const COMMON_KEYS: (keyof WidgetSettings)[] = [
    'theme', 'background_color', 'primary_color', 'primary_text_color', 'secondary_color', 'secondary_text_color',
    'font', 'radius', 'padding', 'locale', 'autoresize',
];

const TICKET_KEYS: (keyof WidgetSettings)[] = [
    'sections', 'products', 'categories', 'occurrence_id', 'promo_code', 'hide_promo_code', 'hide_category_titles',
    'show_lineup', 'continue_button_text', 'checkout',
];

const TYPE_KEYS: Record<WidgetType, (keyof WidgetSettings)[]> = {
    TICKETS: [...COMMON_KEYS, ...TICKET_KEYS],
    BUTTON: [...COMMON_KEYS.filter(key => key !== 'autoresize'), ...TICKET_KEYS, 'button_text'],
    LINEUP: [...COMMON_KEYS, 'occurrence_id'],
    SCHEDULE: [...COMMON_KEYS, 'occurrence_id'],
    EVENTS: [...COMMON_KEYS, 'layout', 'limit', 'event_category', 'show_past', 'open_in', 'checkout', 'continue_button_text', 'hide_promo_code'],
};

const HOST_KEYS: Record<WidgetType, (keyof WidgetSettings)[]> = {
    TICKETS: ['autoresize'],
    BUTTON: ['button_text', 'secondary_color', 'secondary_text_color', 'radius'],
    LINEUP: ['autoresize'],
    SCHEDULE: ['autoresize'],
    EVENTS: ['autoresize'],
};

const TYPE_ATTRIBUTE: Record<WidgetType, string | null> = {
    TICKETS: null,
    BUTTON: 'button',
    LINEUP: 'lineup',
    SCHEDULE: 'schedule',
    EVENTS: 'events',
};

const isEmpty = (key: keyof WidgetSettings, value: unknown) => {
    if (value === undefined || value === null || value === '') return true;
    if (Array.isArray(value)) return value.length === 0;
    if (key === 'autoresize') return value === true;
    if (typeof value === 'boolean') return value === false;
    return false;
};

export const settingsForType = (type: WidgetType, settings: WidgetSettings): WidgetSettings => {
    const result: WidgetSettings = {};
    TYPE_KEYS[type].forEach((key) => {
        const value = settings[key];
        if (!isEmpty(key, value)) {
            (result as Record<string, unknown>)[key] = value;
        }
    });
    if (result.sections?.length === 1 && result.sections[0] === 'tickets') {
        delete result.sections;
    }
    return result;
};

const attributeValue = (value: unknown) => Array.isArray(value) ? value.join(',') : String(value);

export const widgetAttributes = (
    target: WidgetTarget,
    type: WidgetType,
    settings: WidgetSettings,
    savedWidgetId?: string | null,
): [string, string][] => {
    const attributes: [string, string][] = 'eventId' in target
        ? [['data-hievents-id', target.eventId]]
        : [['data-hievents-organizer-id', target.organizerId]];

    const typeAttribute = TYPE_ATTRIBUTE[type];
    if (typeAttribute) {
        attributes.push(['data-hievents-widget-type', typeAttribute]);
    }

    const relevant = settingsForType(type, settings);
    const keys = savedWidgetId
        ? HOST_KEYS[type].filter(key => key in relevant)
        : (Object.keys(relevant) as (keyof WidgetSettings)[]);

    if (savedWidgetId) {
        attributes.push(['data-hievents-widget', savedWidgetId]);
    }

    keys.forEach((key) => {
        attributes.push([settingAttributeName(key), attributeValue(relevant[key])]);
    });

    return attributes;
};

const escapeHtmlAttribute = (value: string) => value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');

const jsxAttributeValue = (value: string) => /["{&]/.test(value)
    ? `{${JSON.stringify(value)}}`
    : `"${value}"`;

export const widgetScriptUrl = () => {
    if (typeof window === 'undefined') {
        return '/widget.js';
    }
    return `${window.location.protocol}//${window.location.host}/widget.js`;
};

export const htmlScriptTag = (scriptUrl: string) => `<script async src="${scriptUrl}"></script>`;

export const htmlWidgetTag = (attributes: [string, string][]) => {
    const lines = attributes
        .map(([name, value]) => `  ${name}="${escapeHtmlAttribute(value)}"`)
        .join('\n');

    return `<div class="hievents-widget"\n${lines}\n></div>`;
};

const elementAttributeName = (name: string) => {
    const plain = name.replace('data-hievents-', '');
    return plain === 'id' ? 'event-id' : plain;
};

const elementAttributeLines = (attributes: [string, string][], indent: string, format: (value: string) => string) => attributes
    .map(([name, value]) => `${indent}${elementAttributeName(name)}=${format(value)}`)
    .join('\n');

export const reactComponent = (attributes: [string, string][], scriptUrl: string) => {
    const lines = elementAttributeLines(attributes, '      ', jsxAttributeValue);

    return `import { useEffect } from 'react';

const WIDGET_SCRIPT = '${scriptUrl}';

export default function HiEventsWidget() {
  useEffect(() => {
    if (!document.querySelector(\`script[src="\${WIDGET_SCRIPT}"]\`)) {
      const script = document.createElement('script');
      script.src = WIDGET_SCRIPT;
      script.async = true;
      document.head.appendChild(script);
    }
  }, []);

  return (
    <hievents-widget
${lines}
    />
  );
}`;
};

export const reactUsage = () => `import HiEventsWidget from './HiEventsWidget';

<HiEventsWidget />`;

export const trackingSnippet = () => `<script>
  window.addEventListener('hievents:order-completed', function (event) {
    var order = event.detail;
    if (order.status !== 'COMPLETED') {
      return;
    }
    if (window.gtag) {
      gtag('event', 'purchase', { currency: order.currency, value: order.total });
    }
    if (window.fbq) {
      fbq('track', 'Purchase', { currency: order.currency, value: order.total });
    }
  });
</script>`;

export const combinedSnippet = (attributes: [string, string][], scriptUrl: string) =>
    `${htmlWidgetTag(attributes)}\n${htmlScriptTag(scriptUrl)}`;

const ATTRIBUTE_DESCRIPTIONS: Record<string, string> = {
    'data-hievents-id': 'The event to show.',
    'data-hievents-organizer-id': 'The organizer whose events are listed.',
    'data-hievents-widget-type': 'Widget mode: button, lineup, schedule or events. Omit for the ticket widget.',
    'data-hievents-widget': 'A saved widget. Its settings come from the Hi.Events dashboard, and the other attributes on this element take priority over them.',
    'data-hievents-theme': '"event" uses the colours and font of the Hi.Events event or organizer page.',
    'data-hievents-background-color': 'Widget background colour.',
    'data-hievents-primary-color': 'Border colour.',
    'data-hievents-primary-text-color': 'Text colour.',
    'data-hievents-secondary-color': 'Button colour.',
    'data-hievents-secondary-text-color': 'Button text colour.',
    'data-hievents-font': 'Font family.',
    'data-hievents-radius': 'Corner radius in pixels.',
    'data-hievents-padding': 'Inner padding in pixels.',
    'data-hievents-locale': 'Language code. Omit to follow the visitor\'s browser language.',
    'data-hievents-autoresize': '"false" stops the widget growing to fit its content. It fills the height you give this element instead.',
    'data-hievents-sections': 'Comma-separated content to show, in order: tickets, header, about, location, organizer, lineup, schedule, custom.',
    'data-hievents-products': 'Comma-separated product ids to show. Others are hidden.',
    'data-hievents-categories': 'Comma-separated product category ids to show.',
    'data-hievents-occurrence-id': 'Pins a recurring event to one date.',
    'data-hievents-promo-code': 'Promo code applied when the widget loads.',
    'data-hievents-hide-promo-code': '"true" hides the "Have a promo code?" link.',
    'data-hievents-hide-category-titles': '"true" hides product category names.',
    'data-hievents-show-lineup': '"true" shows a lineup strip above the tickets.',
    'data-hievents-continue-button-text': 'Label of the button that starts checkout.',
    'data-hievents-button-text': 'Label of the button rendered on the page (button mode).',
    'data-hievents-checkout': '"new-tab" opens checkout in a new tab instead of a pop-up on this page.',
    'data-hievents-layout': 'Events list layout: list, grid or compact.',
    'data-hievents-limit': 'Events per page.',
    'data-hievents-event-category': 'Only list events in this category.',
    'data-hievents-show-past': '"true" adds a Past events tab.',
    'data-hievents-open-in': '"page" opens the event page instead of showing tickets in a pop-up.',
};

const WIDGET_KIND: Record<WidgetType, string> = {
    TICKETS: 'ticket widget',
    BUTTON: 'ticket button (opens tickets in a pop-up)',
    LINEUP: 'lineup widget',
    SCHEDULE: 'schedule widget',
    EVENTS: 'events list widget',
};

// eslint-disable-next-line no-control-regex
const UNSAFE_BRIEF_CHARACTERS = /[\u0000-\u001f\u007f-\u009f\u2028\u2029`]/g;

export const agentInstructions = (type: WidgetType, rawAttributes: [string, string][], scriptUrl: string) => {
    const attributes = rawAttributes.map(([name, value]): [string, string] => [name, value.replace(UNSAFE_BRIEF_CHARACTERS, '')]);
    const origin = new URL(scriptUrl, 'https://example.com').origin;
    const elementLines = elementAttributeLines(attributes, '  ', (value) => `"${escapeHtmlAttribute(value)}"`);
    const reference = attributes
        .map(([name, value]) => `- \`${name}="${value}"\`: ${ATTRIBUTE_DESCRIPTIONS[name] ?? ''}`)
        .join('\n');
    const sellsTickets = type !== 'LINEUP' && type !== 'SCHEDULE';
    const fixedHeight = attributes.some(([name, value]) => name === 'data-hievents-autoresize' && value === 'false');

    return `# Add the Hi.Events ${WIDGET_KIND[type]} to this website

Embed the Hi.Events ${WIDGET_KIND[type]} on the page where it should appear. Copy the code exactly; don't change attribute values or rename attributes.

## 1. Load the script once per page

Put this in the site's <head>, or just before </body>. Load it only once, even if the page has several widgets.

\`\`\`html
${htmlScriptTag(scriptUrl)}
\`\`\`

## 2. Place the widget

Put this element where the widget should appear. Give its container the full available width.

\`\`\`html
${htmlWidgetTag(attributes)}
\`\`\`

## Frameworks and site builders

- React, Next.js, Vue, Svelte and other component frameworks: render the custom element instead of the div. It takes the same attributes without the \`data-hievents-\` prefix (\`data-hievents-id\` becomes \`event-id\`), and reloads when an attribute changes:

\`\`\`html
<hievents-widget
${elementLines}
></hievents-widget>
\`\`\`

  Load the script once, globally (for example with Next.js \`<Script src="${scriptUrl}" strategy="afterInteractive" />\` in the root layout). With TypeScript, declare \`hievents-widget\` as an intrinsic JSX element. Widgets added after the page loads are picked up automatically.
- Server-side rendering is fine: render the element on the server and the script activates it in the browser.
- Site builders (WordPress, Webflow, Squarespace, Wix, Shopify, Framer): paste the element and the script together into the builder's custom HTML or code embed block.
- Don't wrap the widget in your own iframe. ${fixedHeight
        ? 'It creates its own iframe, which fills the widget element because autoresize is off, so give the widget element the height it should take.'
        : 'It creates its own iframe and resizes to fit its content, so don\'t give it a fixed height.'}
- If the site sends a Content-Security-Policy header, allow \`${origin}\` in \`script-src\` and \`frame-src\`.
${sellsTickets ? `
## Optional: track sales in your analytics

The widget dispatches DOM events on the widget element. They bubble to \`window\`:
- \`hievents:checkout-opened\`
- \`hievents:checkout-step\` with \`detail.step\`
- \`hievents:checkout-closed\`
- \`hievents:order-completed\` with \`detail\` = { eventId, currency, total, quantity, status }

\`\`\`html
${trackingSnippet()}
\`\`\`
` : ''}
${sellsTickets ? `## Optional: prefill buyer details

If the visitor is signed in to this site, you can prefill checkout by adding \`data-hievents-prefill-email\`, \`data-hievents-prefill-first-name\` and \`data-hievents-prefill-last-name\` to the widget element. They end up in the widget's iframe URL, so only set them for the signed-in visitor's own details.

` : ''}## Attributes in this embed

${reference}

## Check your work

- The widget appears where it was placed, at full width, with no errors in the browser console.
${sellsTickets ? '- Selecting a ticket and continuing opens checkout (in a pop-up on this site unless data-hievents-checkout="new-tab").\n' : ''}- It still works after navigating between pages, if the site is a single-page app.
`;
};
