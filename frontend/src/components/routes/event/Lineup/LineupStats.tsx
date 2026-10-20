import {useMemo} from "react";
import {t} from "@lingui/macro";
import {Event} from "../../../../types.ts";
import {EventLineup} from "../../../../api/lineup.client.ts";
import {groupScheduleByDay} from "../../../../utilites/lineupSchedule.ts";
import {scheduleCoverage} from "./ScheduleBuilder/scheduleDays.ts";
import classes from "./Lineup.module.scss";

interface LineupStatsProps {
    event: Event;
    lineup: EventLineup;
    lineupLabel: string;
    occurrenceDays?: ReadonlySet<string>;
}

const scheduledHint = (total: number, unscheduled: number, offDateOnly: number): string => {
    if (total === 0) {
        return t`Nobody yet`;
    }
    if (offDateOnly > 0) {
        return t`${offDateOnly} not on an event date`;
    }
    return unscheduled > 0 ? t`${unscheduled} without a time` : t`Everyone has a time`;
};

export const LineupStats = ({event, lineup, lineupLabel, occurrenceDays}: LineupStatsProps) => {
    const stats = useMemo(() => {
        const {scheduled, offDateOnly} = scheduleCoverage(lineup, event.timezone, occurrenceDays);
        const hidden = lineup.entries.filter(entry => entry.is_hidden).length;
        const days = groupScheduleByDay(lineup.schedule_items, event.timezone).length;
        const unscheduled = lineup.entries.length - scheduled - offDateOnly;
        const slots = lineup.schedule_items.length;
        const stages = lineup.spaces.length;

        return [
            {
                label: lineupLabel,
                value: lineup.entries.length,
                hint: lineup.entries.length === 0 ? t`Nobody yet` : hidden > 0 ? t`${hidden} hidden` : t`All visible`,
                warn: false,
            },
            {
                label: t`Scheduled`,
                value: `${scheduled}/${lineup.entries.length}`,
                hint: scheduledHint(lineup.entries.length, unscheduled, offDateOnly),
                warn: unscheduled > 0 || offDateOnly > 0,
            },
            {
                label: t`Time slots`,
                value: slots,
                hint: days === 0 ? t`None yet` : days === 1 ? t`Across 1 day` : t`Across ${days} days`,
                warn: false,
            },
            {
                label: t`Stages & rooms`,
                value: stages,
                hint: stages > 0 ? lineup.spaces.map(space => space.name).join(', ') : t`Optional`,
                warn: false,
            },
        ];
    }, [event.timezone, lineup, lineupLabel, occurrenceDays]);

    return (
        <div className={classes.stats} data-testid="lineup-stats">
            {stats.map(stat => (
                <div key={stat.label} className={classes.stat}>
                    <span className={classes.statLabel}>{stat.label}</span>
                    <span className={classes.statValue}>{stat.value}</span>
                    <span className={classes.statHint} data-warn={stat.warn || undefined}>{stat.hint}</span>
                </div>
            ))}
        </div>
    );
};
