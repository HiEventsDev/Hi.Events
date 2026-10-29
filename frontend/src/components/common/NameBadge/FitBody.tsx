import {ReactNode, useLayoutEffect, useRef} from "react";
import {isSsr} from "../../../utilites/helpers.ts";

const MIN_SCALE = 0.5;

const fitContent = (body: HTMLElement, content: HTMLElement) => {
    const styles = window.getComputedStyle(body);
    const available = body.clientHeight - parseFloat(styles.paddingTop) - parseFloat(styles.paddingBottom);
    const natural = content.offsetHeight;
    const scale = available > 0 && natural > available ? Math.max(MIN_SCALE, available / natural) : 1;
    content.style.transform = scale < 1 ? `scale(${scale})` : '';
};

interface FitBodyProps {
    className: string;
    contentClassName: string;
    children: ReactNode;
}

export const FitBody = ({className, contentClassName, children}: FitBodyProps) => {
    const bodyRef = useRef<HTMLElement>(null);
    const contentRef = useRef<HTMLDivElement>(null);

    useLayoutEffect(() => {
        const body = bodyRef.current;
        const content = contentRef.current;
        if (isSsr() || !body || !content) {
            return;
        }

        fitContent(body, content);

        const observer = new ResizeObserver(() => fitContent(body, content));
        observer.observe(body);
        observer.observe(content);

        let cancelled = false;
        document.fonts?.ready.then(() => {
            if (!cancelled) {
                fitContent(body, content);
            }
        });

        return () => {
            cancelled = true;
            observer.disconnect();
        };
    }, []);

    return (
        <main ref={bodyRef} className={className}>
            <div ref={contentRef} className={contentClassName}>
                {children}
            </div>
        </main>
    );
};
