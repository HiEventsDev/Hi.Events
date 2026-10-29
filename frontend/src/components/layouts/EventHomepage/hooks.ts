import {useEffect, useState} from "react";

const RAIL_EDGE_GAP = 24;
export const WIDE_LAYOUT_QUERY = '(min-width: 1080px)';

export const useInViewport = (node: Element | null, threshold: number): boolean | null => {
    const [inView, setInView] = useState<boolean | null>(null);

    useEffect(() => {
        if (!node) {
            setInView(null);
            return;
        }
        const observer = new IntersectionObserver(([entry]) => setInView(entry.isIntersecting), {threshold});
        observer.observe(node);

        return () => observer.disconnect();
    }, [node, threshold]);

    return inView;
};

export const useStickyRail = (node: HTMLElement | null) => {
    useEffect(() => {
        if (!node) {
            return;
        }
        const wide = window.matchMedia(WIDE_LAYOUT_QUERY);
        let offset = 0;
        let lastScrollY = window.scrollY;
        let frame = 0;

        const bounds = () => {
            const maxTop = parseFloat(getComputedStyle(node).getPropertyValue('--rail-max-top')) || RAIL_EDGE_GAP;
            const minTop = Math.min(maxTop, window.innerHeight - node.offsetHeight - RAIL_EDGE_GAP);
            return {minTop, maxTop};
        };
        const apply = (nextOffset: number) => {
            const {minTop, maxTop} = bounds();
            const top = Math.min(maxTop, Math.max(minTop, maxTop - nextOffset));
            offset = maxTop - top;
            node.style.setProperty('--rail-top', `${top}px`);
        };
        const onScroll = () => {
            if (frame) {
                return;
            }
            frame = requestAnimationFrame(() => {
                frame = 0;
                const scrollY = window.scrollY;
                if (wide.matches) {
                    apply(offset + (scrollY - lastScrollY));
                }
                lastScrollY = scrollY;
            });
        };
        const onResize = () => wide.matches && apply(offset);
        const onFocus = (focusEvent: FocusEvent) => {
            if (!wide.matches || !(focusEvent.target instanceof HTMLElement)) {
                return;
            }
            const {maxTop} = bounds();
            const rect = focusEvent.target.getBoundingClientRect();
            if (rect.top < maxTop) {
                apply(offset - (maxTop - rect.top));
            } else if (rect.bottom > window.innerHeight - RAIL_EDGE_GAP) {
                apply(offset + (rect.bottom - window.innerHeight + RAIL_EDGE_GAP));
            }
        };

        const observer = new ResizeObserver(onResize);
        observer.observe(node);
        window.addEventListener('scroll', onScroll, {passive: true});
        window.addEventListener('resize', onResize);
        node.addEventListener('focusin', onFocus);

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onResize);
            node.removeEventListener('focusin', onFocus);
        };
    }, [node]);
};
