import {CSSProperties, RefObject, useEffect, useLayoutEffect, useState} from "react";

const WIDGET_THEME_TOKENS = [
    '--hi-bg',
    '--hi-text',
    '--hi-accent',
    '--hi-accent-contrast',
    '--hi-card-bg',
    '--hi-border',
    '--hi-border-strong',
    '--hi-hairline',
    '--hi-soft-accent',
    '--hi-soft-accent-strong',
    '--hi-soft-surface',
    '--hi-inset-bg',
    '--hi-muted',
    '--hi-danger',
    '--hi-danger-soft',
    '--hi-danger-text',
    '--hi-warn',
    '--hi-warn-soft',
    '--hi-warn-text',
];

const isTransparent = (color: string): boolean =>
    color === '' || color === 'transparent' || /^rgba\(.*,\s*0\)$/.test(color);

const nearestOpaqueBackground = (element: Element): string | null => {
    for (let node: Element | null = element; node; node = node.parentElement) {
        const background = window.getComputedStyle(node).backgroundColor;
        if (!isTransparent(background)) {
            return background;
        }
    }
    return null;
};

const useIsomorphicLayoutEffect = typeof window === 'undefined' ? useEffect : useLayoutEffect;

const sameStyle = (a: CSSProperties | undefined, b: CSSProperties): boolean =>
    a !== undefined && JSON.stringify(a) === JSON.stringify(b);

export const usePortalThemeStyle = (sourceRef: RefObject<HTMLElement | null>, isPortalOpen: boolean): CSSProperties | undefined => {
    const [style, setStyle] = useState<CSSProperties>();

    useIsomorphicLayoutEffect(() => {
        const source = sourceRef.current;
        if (!source) {
            return;
        }
        const computed = window.getComputedStyle(source);
        const tokens = WIDGET_THEME_TOKENS
            .map(token => [token, computed.getPropertyValue(token).trim()])
            .filter(([, value]) => value !== '');

        const surface = nearestOpaqueBackground(source);

        const next = {
            ...Object.fromEntries(tokens),
            ...(surface ? {'--hi-bg': surface} : {}),
            fontFamily: computed.fontFamily,
        } as CSSProperties;

        setStyle(current => (sameStyle(current, next) ? current : next));
    }, [isPortalOpen, sourceRef]);

    return style;
};
