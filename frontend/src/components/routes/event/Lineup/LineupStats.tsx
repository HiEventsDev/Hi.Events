import {useMemo} from "react";
import {t} from "@lingui/macro";
import {Event} from "../../../../types.ts";
import {EventLineup} from "../../../../api/lineup.client.ts";
import {groupScheduleByDay} from "../../../../utilites/lineupSchedule.ts";
import classes from "./Lineup.module.scss";

interface LineupStatsProps {
    event: Event;
    lineup: EventLineup;
    lineupLabel: string;
}

export const LineupStats = ({event, lineup, lineupLabel}: LineupStatsProps) => {
    const stats = useMemo(() => {
        const scheduledIds = new Set(lineup.schedule_items.flatMap(item => item.lineup_entry_ids));
        const scheduled = lineup.entries.filter(entry => scheduledIds.has(entry.id)).length;
        const hidden = lineup.entries.filter(entry => entry.is_hidden).length;
        const days = groupScheduleByDay(lineup.schedule_items, event.timezone).length;
        const unscheduled = lineup.entries.length - scheduled;
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
                hint: lineup.entries.length === 0 ? t`Nobody yet` : unscheduled > 0 ? t`${unscheduled} without a time` : t`Everyone has a time`,
                warn: unscheduled > 0,
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
    }, [event.timezone, lineup, lineupLabel]);

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
