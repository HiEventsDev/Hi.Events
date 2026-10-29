import {useEffect, useRef} from "react";
import {createPortal} from "react-dom";
import {BadgePrinter} from "../../../hooks/useBadgePrinter.ts";
import {BADGE_SIZE_DIMENSIONS, getBadgeImages} from "../../../utilites/badges.ts";
import {waitForBadgesToRender} from "../../../utilites/badgePrinting.ts";
import {isSsr} from "../../../utilites/helpers.ts";
import {BadgePrintSheets} from "./BadgePrintSheets.tsx";
import classes from "./BadgePrintPortal.module.scss";

const PRINTING_BODY_CLASS = 'hi-badge-printing';

export const BadgePrintPortal = ({printer}: { printer: BadgePrinter }) => {
    const rootRef = useRef<HTMLDivElement>(null);
    const {job, layout, finishJob} = printer;

    useEffect(() => {
        if (!job || !layout) {
            return;
        }

        let cancelled = false;
        const handleAfterPrint = () => {
            document.body.classList.remove(PRINTING_BODY_CLASS);
            if (!cancelled) {
                finishJob(job, true);
            }
        };

        waitForBadgesToRender(rootRef.current, layout.font_family).then(() => {
            if (cancelled) {
                return;
            }
            document.body.classList.add(PRINTING_BODY_CLASS);
            window.addEventListener('afterprint', handleAfterPrint, {once: true});
            window.print();
        });

        return () => {
            cancelled = true;
            window.removeEventListener('afterprint', handleAfterPrint);
            document.body.classList.remove(PRINTING_BODY_CLASS);
        };
    }, [job?.id]);

    if (isSsr() || !job || !layout) {
        return null;
    }

    const labelSize = printer.settings.labelSize;
    const pageSizeOverride = labelSize !== 'DESIGN' && labelSize !== 'CUSTOM' && labelSize !== layout.size
        ? BADGE_SIZE_DIMENSIONS[labelSize]
        : undefined;

    return createPortal(
        <div ref={rootRef} className={classes.root} data-badge-print-root="" data-testid="badge-print-portal">
            <BadgePrintSheets
                layout={layout}
                badges={[job.badge]}
                eventName={printer.eventName}
                images={getBadgeImages(printer.images)}
                timezone={printer.timezone}
                sheet="NONE"
                backPrinting={printer.backPrinting}
                pageSizeOverride={pageSizeOverride}
            />
        </div>,
        document.body,
    );
};
