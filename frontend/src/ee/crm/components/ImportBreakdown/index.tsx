import {formatCount} from "../../utilites/format.ts";
import classes from "./ImportBreakdown.module.scss";

export const ImportBreakdown = ({rows}: { rows: { label: string, value: number }[] }) => (
    <dl className={classes.breakdown}>
        {rows.filter(row => row.value > 0).map(row => (
            <div key={row.label} className={classes.breakdownRow}>
                <dt>{row.label}</dt>
                <dd>{formatCount(row.value)}</dd>
            </div>
        ))}
    </dl>
);
