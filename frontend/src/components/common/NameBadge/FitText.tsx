import {CSSProperties, ReactNode, useLayoutEffect, useRef} from "react";
import {isSsr} from "../../../utilites/helpers.ts";

const MIN_SINGLE_LINE_RATIO = 0.62;
const MIN_WRAPPED_RATIO = 0.42;
const STEP = 0.94;

type MaxLines = 1 | 2;

interface FitState {
    element: HTMLElement;
    maxLines: MaxLines;
    base: number;
    size: number;
}

const pending = new Map<HTMLElement, MaxLines>();
let isFlushScheduled = false;

const overflowsWidth = (state: FitState) => state.element.scrollWidth > state.element.clientWidth + 0.5;

const exceedsTwoLines = (state: FitState) => {
    const lineHeight = parseFloat(window.getComputedStyle(state.element).lineHeight) || state.size * 1.15;
    return state.element.scrollHeight > lineHeight * 2.05 || overflowsWidth(state);
};

const shrinkWhile = (states: FitState[], overflows: (state: FitState) => boolean, minRatio: number) => {
    let active = states.filter((state) => overflows(state) && state.size > state.base * minRatio);
    while (active.length > 0) {
        active.forEach((state) => {
            state.size *= STEP;
            state.element.style.fontSize = `${state.size}px`;
        });
        active = active.filter((state) => overflows(state) && state.size > state.base * minRatio);
    }
};

const flushFits = () => {
    isFlushScheduled = false;
    const entries = Array.from(pending).filter(([element]) => element.isConnected);
    pending.clear();

    entries.forEach(([element]) => {
        element.style.fontSize = '';
        element.style.whiteSpace = 'nowrap';
    });

    const states: FitState[] = entries
        .map(([element, maxLines]) => {
            const base = parseFloat(window.getComputedStyle(element).fontSize);
            return {element, maxLines, base, size: base};
        })
        .filter((state) => state.base > 0 && state.element.clientWidth > 0);

    shrinkWhile(states, overflowsWidth, MIN_SINGLE_LINE_RATIO);

    const wrapping = states.filter((state) => state.maxLines === 2 && overflowsWidth(state));
    wrapping.forEach((state) => {
        state.element.style.whiteSpace = 'normal';
    });
    shrinkWhile(wrapping, exceedsTwoLines, MIN_WRAPPED_RATIO);

    if (document.fonts?.status === 'loading') {
        document.fonts.ready.then(() => entries.forEach(([element, maxLines]) => scheduleFit(element, maxLines)));
    }
};

const scheduleFit = (element: HTMLElement, maxLines: MaxLines) => {
    pending.set(element, maxLines);
    if (!isFlushScheduled) {
        isFlushScheduled = true;
        queueMicrotask(flushFits);
    }
};

interface FitTextProps {
    children: ReactNode;
    className?: string;
    style?: CSSProperties;
    fitKey: string;
    dataSize?: string;
    maxLines?: MaxLines;
}

export const FitText = ({children, className, style, fitKey, dataSize, maxLines = 2}: FitTextProps) => {
    const ref = useRef<HTMLSpanElement>(null);

    useLayoutEffect(() => {
        if (isSsr() || !ref.current) {
            return;
        }

        const element = ref.current;
        const container = element.parentElement ?? element;
        let fittedWidth = container.clientWidth;
        scheduleFit(element, maxLines);

        const observer = new ResizeObserver(() => {
            if (container.clientWidth !== fittedWidth) {
                fittedWidth = container.clientWidth;
                scheduleFit(element, maxLines);
            }
        });
        observer.observe(container);

        return () => {
            observer.disconnect();
            pending.delete(element);
        };
    }, [fitKey, maxLines]);

    return (
        <span ref={ref} className={className} style={style} data-size={dataSize} data-fit-text="">
            {children}
        </span>
    );
};
