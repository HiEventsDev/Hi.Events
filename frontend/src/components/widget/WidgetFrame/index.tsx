import {CSSProperties, ReactNode, useEffect, useRef} from "react";
import {sendHeightToParent} from "../../../utilites/iframeResize.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {EmbeddedDialogOffsetProvider, FrameBindings} from "../EmbeddedDialogOffset.tsx";
import classes from "./WidgetFrame.module.scss";

interface WidgetFrameProps {
    style: CSSProperties;
    mode: 'light' | 'dark';
    children: ReactNode;
    testId?: string;
}

const useReportContentHeight = (minHeight: number) => {
    const contentRef = useRef<HTMLDivElement>(null);
    const minHeightRef = useRef(minHeight);
    const reportRef = useRef<() => void>(() => undefined);

    useEffect(() => {
        const element = contentRef.current;
        if (!element || typeof ResizeObserver === 'undefined') {
            return;
        }

        let frame = 0;
        let lastHeight = -1;
        const report = () => {
            frame = 0;
            const height = Math.max(Math.ceil(element.getBoundingClientRect().height), minHeightRef.current);
            if (height !== lastHeight) {
                lastHeight = height;
                sendHeightToParent(height);
            }
        };
        reportRef.current = report;
        const observer = new ResizeObserver(() => {
            if (!frame) {
                frame = requestAnimationFrame(report);
            }
        });
        observer.observe(element);
        report();

        return () => {
            observer.disconnect();
            reportRef.current = () => undefined;
            if (frame) {
                cancelAnimationFrame(frame);
            }
        };
    }, []);

    useEffect(() => {
        minHeightRef.current = minHeight;
        reportRef.current();
    }, [minHeight]);

    return contentRef;
};

const FrameContent = ({style, mode, children, testId, frame}: WidgetFrameProps & { frame: FrameBindings }) => {
    const contentRef = useReportContentHeight(frame.minHeight);

    return (
        <main
            ref={frame.ref}
            className={`${classes.frame} hi-widget-tokens`}
            style={style}
            data-mode={mode}
            data-testid={testId}
            onPointerDownCapture={frame.onPointerDownCapture}
            onFocusCapture={frame.onFocusCapture}
        >
            <div ref={contentRef} className={classes.content}>
                {children}
                <PoweredByFooter className={classes.poweredBy}/>
            </div>
        </main>
    );
};

export const WidgetFrame = (props: WidgetFrameProps) => (
    <EmbeddedDialogOffsetProvider>
        {(frame) => <FrameContent {...props} frame={frame}/>}
    </EmbeddedDialogOffsetProvider>
);
