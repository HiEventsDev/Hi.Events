import {useMemo} from "react";
import {t} from "@lingui/macro";
import {Avatar} from "@mantine/core";
import {IconCalendarPlus} from "@tabler/icons-react";
import {Event} from "../../../../types.ts";
import {EventLineup, LineupEntry, ScheduleItem} from "../../../../api/lineup.client.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {initialsFor, profilePhoto, scheduleItemTitle} from "../../../../utilites/lineup.ts";
import {groupScheduleByDay} from "../../../../utilites/lineupSchedule.ts";
import {CreateButton} from "../../../common/CreateButton";
import {SPACE_COLORS} from "./ScheduleBuilder/scheduleConstants.ts";
import classes from "./Lineup.module.scss";

interface AgendaListProps {
    event: Event;
    lineup: EventLineup;
    onEdit: (item: ScheduleItem) => void;
    onAdd: () => void;
}

export const AgendaList = ({event, lineup, onEdit, onAdd}: AgendaListProps) => {
    const tz = event.timezone;
    const days = useMemo(() => groupScheduleByDay(lineup.schedule_items, tz), [lineup.schedule_items, tz]);
    const entriesById = useMemo(
        () => new Map<number, LineupEntry>(lineup.entries.map(entry => [entry.id, entry])),
        [lineup.entries],
    );
    const spaceIndex = new Map(lineup.spaces.map((space, index) => [space.id, index]));

    if (days.length === 0) {
        return (
            <div className={classes.panelEmpty}>
                <IconCalendarPlus size={22} stroke={1.5}/>
                <p>{t`No time slots yet. Add one here or drag someone onto the timetable.`}</p>
                <CreateButton size="xs" variant="light" onClick={onAdd}>
                    {t`Add time slot`}
                </CreateButton>
            </div>
        );
    }

    return (
        <div className={classes.agendaAdmin} data-testid="lineup-agenda">
            {days.map(day => {
                const slotCount = day.items.length;
                return (
                    <section key={day.key} className={classes.agendaDay}>
                        <header className={classes.agendaDayHeader}>
                            <span>{formatDateWithLocale(day.items[0].starts_at, 'weekdayDate', tz)}</span>
                            <span className={classes.summaryMuted}>{slotCount === 1 ? t`1 slot` : t`${slotCount} slots`}</span>
                        </header>
                        {day.items.map(item => {
                            const performers = item.lineup_entry_ids
                                .map(id => entriesById.get(id))
                                .filter((entry): entry is LineupEntry => !!entry);
                            const space = lineup.spaces.find(candidate => candidate.id === item.event_space_id);
                            const color = space ? SPACE_COLORS[(spaceIndex.get(space.id) ?? 0) % SPACE_COLORS.length] : 'gray';

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    className={classes.agendaAdminRow}
                                    onClick={() => onEdit(item)}
                                    data-testid={`lineup-agenda-item-${item.id}`}
                                >
                                    <span className={classes.agendaAdminTime}>
                                        {formatDateWithLocale(item.starts_at, 'timeOnly', tz)}
                                        <span>{formatDateWithLocale(item.ends_at, 'timeOnly', tz)}</span>
                                    </span>
                                    <span className={classes.agendaAdminMain}>
                                        <Avatar.Group spacing={8}>
                                            {performers.slice(0, 3).map(entry => (
                                                <Avatar key={entry.id} src={profilePhoto(entry.profile)?.url} alt="" size={28} radius="xl" color="primary">
                                                    {initialsFor(entry.profile.name)}
                                                </Avatar>
                                            ))}
                                        </Avatar.Group>
                                        <span className={classes.entryText}>
                                            <span className={classes.entryName}>{scheduleItemTitle(item, entriesById) || t`Untitled`}</span>
                                            {item.title && performers.length > 0 && (
                                                <span className={classes.entryTagline}>{performers.map(entry => entry.profile.name).join(', ')}</span>
                                            )}
                                        </span>
                                    </span>
                                    <span className={classes.agendaAdminSpace}>
                                        <span className={classes.spaceSwatch} style={{background: `var(--mantine-color-${color}-5)`}}/>
                                        <span className={classes.agendaAdminSpaceName} title={space?.name}>{space?.name ?? t`No stage`}</span>
                                    </span>
                                </button>
                            );
                        })}
                    </section>
                );
            })}
        </div>
    );
};
