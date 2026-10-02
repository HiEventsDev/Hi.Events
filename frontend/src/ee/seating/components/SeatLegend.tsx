import {t} from "@lingui/macro";
import {SeatMapBand} from "./lib/types.ts";
import classes from "./SeatLegend.module.scss";

const UNAVAILABLE_COLOR = '#dcdce2';

interface SeatLegendProps {
    bands: SeatMapBand[];
    layout: 'inline' | 'stacked';
    showUnavailable?: boolean;
    showCompanion?: boolean;
}

export const SeatLegend = ({bands, layout, showUnavailable = false, showCompanion = false}: SeatLegendProps) => (
    <div className={classes.legend} data-layout={layout}>
        {bands.map(band => (
            <span key={band.key} className={classes.item}>
                <span className={classes.swatch} style={{background: band.color}}/>
                <span className={classes.name}>{band.name}</span>
            </span>
        ))}
        {showUnavailable && (
            <span className={classes.item}>
                <span className={classes.swatch} style={{background: UNAVAILABLE_COLOR}}/>{t`Taken`}
            </span>
        )}
        {showCompanion && (
            <span className={classes.item} data-testid="seat-legend-companion">
                <span className={classes.companionSwatch}/>{t`Companion seat (with a wheelchair space)`}
            </span>
        )}
    </div>
);
