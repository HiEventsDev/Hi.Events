import {CSSProperties, ReactNode, useLayoutEffect, useRef} from "react";
import {isSsr} from "../../../utilites/helpers.ts";

const MIN_SINGLE_LINE_RATIO = 0.62;
const MIN_WRAPPED_RATIO = 0.42;
const STEP = 0.94;

const fitElement = (element: HTMLElement, maxLines: 1 | 2) => {
    element.style.fontSize = '';
    element.style.whiteSpace = 'nowrap';

    const baseSize = parseFloat(window.getComputedStyle(element).fontSize);
    if (!baseSize || element.clientWidth === 0) {
        return;
    }

    let size = baseSize;
    while (element.scrollWidth > element.clientWidth + 0.5 && size > baseSize * MIN_SINGLE_LINE_RATIO) {
        size *= STEP;
        element.style.fontSize = `${size}px`;
    }

    if (element.scrollWidth <= element.clientWidth + 0.5 || maxLines === 1) {
        return;
    }

    element.style.whiteSpace = 'normal';
    const exceedsTwoLines = () => {
        const lineHeight = parseFloat(window.getComputedStyle(element).lineHeight) || size * 1.15;
        return element.scrollHeight > lineHeight * 2.05 || element.scrollWidth > element.clientWidth + 0.5;
    };
    while (exceedsTwoLines() && size > baseSize * MIN_WRAPPED_RATIO) {
        size *= STEP;
        element.style.fontSize = `${size}px`;
    }
};

interface FitTextProps {
    children: ReactNode;
    className?: string;
    style?: CSSProperties;
    fitKey: string;
    dataSize?: string;
    maxLines?: 1 | 2;
}

export const FitText = ({children, className, style, fitKey, dataSize, maxLines = 2}: FitTextProps) => {
    const ref = useRef<HTMLSpanElement>(null);

    useLayoutEffect(() => {
        if (isSsr() || !ref.current) {
            return;
        }

        const element = ref.current;
        fitElement(element, maxLines);

        const observer = new ResizeObserver(() => fitElement(element, maxLines));
        observer.observe(element.parentElement ?? element);

        let cancelled = false;
        document.fonts?.ready.then(() => {
            if (!cancelled) {
                fitElement(element, maxLines);
            }
        });

        return () => {
            cancelled = true;
            observer.disconnect();
        };
    }, [fitKey, maxLines]);

    return (
        <span ref={ref} className={className} style={style} data-size={dataSize} data-fit-text="">
            {children}
        </span>
    );
};
