import {getBadgeDimensions, getBadgeFold, getPrintUnitSize} from "../../../utilites/badges.ts";
import {NameBadge, NameBadgeProps} from "./index.tsx";
import classes from "./FoldOverBadge.module.scss";

interface FoldOverBadgeProps extends Omit<NameBadgeProps, 'side' | 'showCutGuide'> {
    showCutGuide?: boolean;
    showFoldLine?: boolean;
}

export const FoldOverBadge = ({showCutGuide, showFoldLine, ...badgeProps}: FoldOverBadgeProps) => {
    const badgeSize = getBadgeDimensions(badgeProps.layout);
    const {width, height} = getPrintUnitSize(badgeSize, 'FOLD_OVER');

    return (
        <div
            className={classes.unit}
            data-fold={getBadgeFold(badgeSize)}
            data-cut-guide={showCutGuide ? 'true' : undefined}
            data-testid="badge-fold-over"
            style={{width: `${width}mm`, height: `${height}mm`}}
        >
            <div className={classes.panel} data-panel="front">
                <NameBadge {...badgeProps} side="front"/>
            </div>
            <div className={classes.panel} data-panel="back">
                <NameBadge {...badgeProps} side="back"/>
            </div>
            {showFoldLine && <span className={classes.foldLine} aria-hidden="true"/>}
        </div>
    );
};
