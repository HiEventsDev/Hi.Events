import {formatDateWithLocale, formatWeekday} from "../../../utilites/dates.ts";
import classes from "./HomepageFrame.module.scss";

export const DatePoster = ({date, tz}: { date: string, tz: string }) => (
    <div className={classes.datePoster} aria-hidden="true">
        <span className={classes.datePosterMonth}>{formatDateWithLocale(date, 'monthShort', tz)}</span>
        <span className={classes.datePosterDay}>{formatDateWithLocale(date, 'dayOfMonth', tz)}</span>
        <span className={classes.datePosterWeekday}>{formatWeekday(date, tz)}</span>
    </div>
);
