import {useEffect, useMemo, useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {Button, Chip, Group, Loader, Switch} from "@mantine/core";
import {IconPrinter} from "@tabler/icons-react";
import dayjs from "dayjs";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventLineup} from "../../../../queries/useGetEventLineup.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {scheduleItemTitle} from "../../../../utilites/lineup.ts";
import {groupScheduleByDay} from "../../../../utilites/lineupSchedule.ts";
import {EventLineup, LineupEntry, ScheduleItem} from "../../../../api/lineup.client.ts";
import {Event} from "../../../../types.ts";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import classes from "./RunSheetPrint.module.scss";

const ALL_DAYS = 'all';
const NO_SPACE = 'none';

const RunSheetPrint = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const lineupQuery = useGetEventLineup(eventId);

    if (eventQuery.isError || lineupQuery.isError) {
        return (
            <div className={classes.state} role="alert">
                {t`We couldn't load this run sheet. Check you have access to this event and try again.`}
            </div>
        );
    }

    if (!eventQuery.data || !lineupQuery.data) {
        return <div className={classes.state}><Loader size="sm"/></div>;
    }

    return <RunSheet event={eventQuery.data} lineup={lineupQuery.data}/>;
};

const RunSheet = ({event, lineup}: { event: Event, lineup: EventLineup }) => {
    const tz = event.timezone;
    const days = useMemo(() => groupScheduleByDay(lineup.schedule_items, tz), [lineup.schedule_items, tz]);
    const entriesById = useMemo(() => new Map(lineup.entries.map(entry => [entry.id, entry])), [lineup.entries]);
    const [selectedDay, setSelectedDay] = useState(ALL_DAYS);
    const [byStage, setByStage] = useState(lineup.spaces.length > 1);

    const visibleDays = days.filter(day => selectedDay === ALL_DAYS || day.key === selectedDay);

    const groupsFor = (items: ScheduleItem[]) => {
        if (!byStage) {
            return [{key: ALL_DAYS, name: null as string | null, items}];
        }
        return [
            ...lineup.spaces.map(space => ({key: String(space.id), name: space.name, items: items.filter(item => item.event_space_id === space.id)})),
            {key: NO_SPACE, name: t`No stage or room`, items: items.filter(item => !item.event_space_id)},
        ].filter(group => group.items.length > 0);
    };

    const eventTitle = event.title;
    const pageTitle = t`Run sheet · ${eventTitle}`;

    useEffect(() => {
        const previousTitle = document.title;
        document.title = pageTitle;
        return () => {
            document.title = previousTitle;
        };
    }, [pageTitle]);

    return (
        <div className={classes.page}>
            <div className={classes.toolbar}>
                <Group gap={6} className={classes.dayChips}>
                    <Chip size="sm" checked={selectedDay === ALL_DAYS} onChange={() => setSelectedDay(ALL_DAYS)}>{t`All days`}</Chip>
                    {days.map(day => (
                        <Chip key={day.key} size="sm" checked={selectedDay === day.key} onChange={() => setSelectedDay(day.key)}>
                            {formatDateWithLocale(day.items[0].starts_at, 'weekdayDate', tz)}
                        </Chip>
                    ))}
                </Group>
                <Group gap="md" className={classes.toolbarActions}>
                    {lineup.spaces.length > 0 && (
                        <Switch size="sm" label={t`Group by stage or room`} checked={byStage} onChange={change => setByStage(change.currentTarget.checked)}/>
                    )}
                    <Button size="sm" leftSection={<IconPrinter size={16}/>} onClick={() => window.print()} data-testid="run-sheet-print-button">
                        {t`Print`}
                    </Button>
                </Group>
            </div>

            <header className={classes.header}>
                <span className={classes.eyebrow}>{t`Run sheet`}</span>
                <h1>{event.title}</h1>
            </header>

            {visibleDays.length === 0 && <p className={classes.empty}>{t`Nothing has been scheduled yet.`}</p>}

            {visibleDays.map(day => (
                <section key={day.key} className={classes.day}>
                    <h2>{formatDateWithLocale(day.items[0].starts_at, 'weekdayDate', tz)}</h2>
                    {groupsFor(day.items).map(group => (
                        <div key={group.key} className={classes.group}>
                            {group.name && <h3>{group.name}</h3>}
                            <RunSheetTable items={group.items} event={event} lineup={lineup} entriesById={entriesById} showStage={!byStage}/>
                        </div>
                    ))}
                </section>
            ))}

            <div className={classes.footer}>
                <PoweredByFooter/>
            </div>
        </div>
    );
};

interface RunSheetTableProps {
    items: ScheduleItem[];
    event: Event;
    lineup: EventLineup;
    entriesById: Map<number, LineupEntry>;
    showStage: boolean;
}

const RunSheetTable = ({items, event, lineup, entriesById, showStage}: RunSheetTableProps) => {
    const tz = event.timezone;
    const spaceName = (item: ScheduleItem) => lineup.spaces.find(space => space.id === item.event_space_id)?.name ?? '';

    return (
        <div className={classes.tableScroll}>
            <table className={classes.table}>
                <thead>
                    <tr>
                        <th className={classes.time}>{t`Start`}</th>
                        <th className={classes.time}>{t`End`}</th>
                        <th className={`${classes.time} ${classes.lengthColumn}`}>{t`Length`}</th>
                        {showStage && <th className={classes.stageColumn}>{t`Stage`}</th>}
                        <th>{t`What`}</th>
                        <th className={classes.notesColumn}>{t`Notes`}</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map(item => {
                        const minutes = dayjs.utc(item.ends_at).diff(dayjs.utc(item.starts_at), 'minute');
                        const people = item.lineup_entry_ids
                            .map(id => entriesById.get(id))
                            .filter((entry): entry is LineupEntry => !!entry);
                        const hiddenNames = people.filter(entry => entry.is_hidden).map(entry => entry.profile.name).join(', ');

                        return (
                            <tr key={item.id}>
                                <td className={classes.time}>{formatDateWithLocale(item.starts_at, 'timeOnly', tz)}</td>
                                <td className={classes.time}>{formatDateWithLocale(item.ends_at, 'timeOnly', tz)}</td>
                                <td className={`${classes.time} ${classes.lengthColumn}`}>{t`${minutes} min`}</td>
                                {showStage && <td className={classes.stageColumn}>{spaceName(item)}</td>}
                                <td>
                                    <strong>{scheduleItemTitle(item, entriesById)}</strong>
                                    {item.title && people.length > 0 && (
                                        <span className={classes.people}>{people.map(entry => entry.profile.name).join(', ')}</span>
                                    )}
                                    {hiddenNames && (
                                        <span className={classes.hidden}>{t`Hidden from event page: ${hiddenNames}`}</span>
                                    )}
                                </td>
                                <td className={classes.notesColumn}/>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
};

export default RunSheetPrint;
