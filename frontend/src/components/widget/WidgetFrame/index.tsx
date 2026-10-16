import {CSSProperties, ReactNode, useEffect, useRef} from "react";
import {sendHeightToParent} from "../../../utilites/iframeResize.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {EmbeddedDialogOffsetProvider} from "../EmbeddedDialogOffset.tsx";
import classes from "./WidgetFrame.module.scss";

interface WidgetFrameProps {
    style: CSSProperties;
    mode: 'light' | 'dark';
    children: ReactNode;
    testId?: string;
}

const useReportContentHeight = () => {
    const contentRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const element = contentRef.current;
        if (!element || typeof ResizeObserver === 'undefined') {
            return;
        }

        let frame = 0;
        let lastHeight = -1;
        const report = () => {
            frame = 0;
            const height = Math.ceil(element.getBoundingClientRect().height);
            if (height !== lastHeight) {
                lastHeight = height;
                sendHeightToParent(height);
            }
        };
        const observer = new ResizeObserver(() => {
            if (!frame) {
                frame = requestAnimationFrame(report);
            }
        });
        observer.observe(element);
        report();

        return () => {
            observer.disconnect();
            if (frame) {
                cancelAnimationFrame(frame);
            }
        };
    }, []);

    return contentRef;
};

export const WidgetFrame = ({style, mode, children, testId}: WidgetFrameProps) => {
    const contentRef = useReportContentHeight();

    return (
        <EmbeddedDialogOffsetProvider>
            {(handlers) => (
                <main className={`${classes.frame} hi-widget-tokens`} style={style} data-mode={mode} data-testid={testId} {...handlers}>
                    <div ref={contentRef} className={classes.content}>
                        {children}
                        <PoweredByFooter className={classes.poweredBy}/>
                    </div>
                </main>
            )}
        </EmbeddedDialogOffsetProvider>
    );
};
