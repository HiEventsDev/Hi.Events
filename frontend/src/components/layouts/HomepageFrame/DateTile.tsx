import {formatDateWithLocale} from "../../../utilites/dates.ts";
import classes from "./HomepageFrame.module.scss";

export const DateTile = ({date, tz, size = 'md'}: { date: string, tz: string, size?: 'md' | 'lg' }) => (
    <span className={classes.dateTile} data-size={size} aria-hidden="true">
        <span className={classes.dateTileMonth}>{formatDateWithLocale(date, 'monthShort', tz)}</span>
        <span className={classes.dateTileDay}>{formatDateWithLocale(date, 'dayOfMonth', tz)}</span>
    </span>
);
