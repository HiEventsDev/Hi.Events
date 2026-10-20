import {useEffect} from "react";

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const focusableWithin = (element: HTMLElement): HTMLElement =>
    element.matches(FOCUSABLE) ? element : element.querySelector<HTMLElement>(FOCUSABLE) ?? element;

const menuButtonFor = (menu: Element): HTMLElement | null => {
    const controller = menu.id ? document.querySelector(`[aria-controls="${CSS.escape(menu.id)}"]`) : null;
    const labelledBy = menu.getAttribute('aria-labelledby')?.split(' ')[0];
    const button = controller ?? (labelledBy ? document.getElementById(labelledBy) : null);

    return button instanceof HTMLElement ? focusableWithin(button) : null;
};

export const findReturnFocusTarget = (opener: Element | null): HTMLElement | null => {
    if (!(opener instanceof HTMLElement) || opener === document.body) {
        return null;
    }

    const menu = opener.closest('[role="menu"]');

    return menu ? menuButtonFor(menu) : opener;
};

export const useReturnFocusOnClose = (opened: boolean) => {
    useEffect(() => {
        if (!opened) {
            return;
        }

        const returnTarget = findReturnFocusTarget(document.activeElement);

        return () => {
            const focusWasLost = !document.activeElement || document.activeElement === document.body;

            if (focusWasLost && returnTarget?.isConnected) {
                returnTarget.focus({preventScroll: true});
            }
        };
    }, [opened]);
};
