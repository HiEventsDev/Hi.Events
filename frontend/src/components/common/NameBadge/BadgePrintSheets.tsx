import {Badge as BadgeData, BadgeLayout} from "../../../types.ts";
import {
    BadgeBackPrinting,
    BadgeImages,
    buildBadgePrintPages,
    computeSheetGrid,
    getBadgeDimensions,
    getPrintUnitSize,
} from "../../../utilites/badges.ts";
import {NameBadge} from "./index.tsx";
import {FoldOverBadge} from "./FoldOverBadge.tsx";
import classes from "./BadgePrintSheets.module.scss";

export interface BadgePrintSheetsProps {
    layout: BadgeLayout;
    badges: BadgeData[];
    eventName: string;
    images: BadgeImages;
    timezone?: string;
    sheet: BadgeLayout['sheet'];
    backPrinting: BadgeBackPrinting;
    showCutGuides?: boolean;
    pageSizeOverride?: { width: number; height: number };
}

export const BadgePrintSheets = ({
                                     layout,
                                     badges,
                                     eventName,
                                     images,
                                     timezone,
                                     sheet,
                                     backPrinting,
                                     showCutGuides,
                                     pageSizeOverride,
                                 }: BadgePrintSheetsProps) => {
    const badgeSize = pageSizeOverride ?? getBadgeDimensions(layout);
    const unitSize = getPrintUnitSize(badgeSize, backPrinting);
    const effectiveLayout: BadgeLayout = pageSizeOverride
        ? {...layout, size: 'CUSTOM', custom_width_mm: pageSizeOverride.width, custom_height_mm: pageSizeOverride.height}
        : layout;
    const grid = computeSheetGrid(unitSize, sheet);
    const pages = buildBadgePrintPages(badges, grid, backPrinting);
    const isFoldOver = backPrinting === 'FOLD_OVER';

    const pageCss = `@page { size: ${grid.pageWidth}mm ${grid.pageHeight}mm; margin: 0; }`;

    return (
        <div className={classes.sheets} data-testid="badge-print-sheets">
            <style>{pageCss}</style>
            {pages.map((page) => (
                <div
                    key={page.key}
                    className={classes.page}
                    data-side={page.side}
                    data-testid="badge-print-page"
                    style={{
                        width: `${grid.pageWidth}mm`,
                        height: `${grid.pageHeight}mm`,
                        paddingTop: `${grid.offsetY}mm`,
                        paddingLeft: `${grid.offsetX}mm`,
                        gridTemplateColumns: `repeat(${grid.columns}, ${unitSize.width}mm)`,
                        gridAutoRows: `${unitSize.height}mm`,
                    }}
                >
                    {page.cells.map((badge, index) => {
                        if (!badge) {
                            return <div key={`${page.key}-empty-${index}`}/>;
                        }
                        const key = `${page.key}-${badge.attendee_id}-${index}`;
                        return isFoldOver
                            ? (
                                <FoldOverBadge
                                    key={key}
                                    layout={effectiveLayout}
                                    badge={badge}
                                    eventName={eventName}
                                    images={images}
                                    timezone={timezone}
                                    showCutGuide={showCutGuides && grid.perPage > 1}
                                    showFoldLine={showCutGuides}
                                />
                            )
                            : (
                                <NameBadge
                                    key={key}
                                    layout={effectiveLayout}
                                    badge={badge}
                                    eventName={eventName}
                                    images={images}
                                    timezone={timezone}
                                    side={page.side === 'back' ? 'back' : 'front'}
                                    showCutGuide={showCutGuides && grid.perPage > 1}
                                />
                            );
                    })}
                </div>
            ))}
        </div>
    );
};
