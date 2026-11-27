import {useEffect, useRef} from "react";
import {createPortal} from "react-dom";
import {BadgePrinter} from "../../../hooks/useBadgePrinter.ts";
import {BADGE_SIZE_DIMENSIONS, getBadgeImages} from "../../../utilites/badges.ts";
import {printBadgeSheets, showOnlyBadgeSheetsInPrint, waitForBadgesToRender} from "../../../utilites/badgePrinting.ts";
import {isSsr} from "../../../utilites/helpers.ts";
import {BadgePrintSheets} from "./BadgePrintSheets.tsx";
import classes from "./BadgePrintPortal.module.scss";

export const BadgePrintPortal = ({printer}: { printer: BadgePrinter }) => {
    const rootRef = useRef<HTMLDivElement>(null);
    const {job, prepared, layout, finishJob, markPreparedRendered} = printer;
    const badge = job?.badge ?? prepared?.badge;
    const fontFamily = layout?.font_family;

    useEffect(() => {
        if (!job || !layout) {
            return;
        }

        let cancelled = false;
        let cancelPrint: (() => void) | null = null;

        waitForBadgesToRender(rootRef.current, layout.font_family).then(() => {
            if (!cancelled) {
                cancelPrint = printBadgeSheets(() => finishJob(job, true));
            }
        });

        return () => {
            cancelled = true;
            cancelPrint?.();
        };
    }, [job?.id]);

    useEffect(() => {
        if (!prepared || !fontFamily) {
            return;
        }

        let cancelled = false;
        let showAllOfPage: (() => void) | null = null;
        waitForBadgesToRender(rootRef.current, fontFamily).then(() => {
            if (!cancelled) {
                showAllOfPage = showOnlyBadgeSheetsInPrint();
                markPreparedRendered(prepared.id);
            }
        });

        return () => {
            cancelled = true;
            showAllOfPage?.();
        };
    }, [prepared, fontFamily, markPreparedRendered]);

    if (isSsr() || !badge || !layout) {
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
                badges={[badge]}
                eventName={printer.eventName}
                images={getBadgeImages(printer.images)}
                timezone={printer.timezone}
                sheet="NONE"
                backPrinting={printer.backPrinting}
                pageSizeOverride={pageSizeOverride}
                fitInsideIosPrintMargins={printer.printsFromATap}
                blackInkOnly={printer.settings.blackInkOnly}
            />
        </div>,
        document.body,
    );
};
