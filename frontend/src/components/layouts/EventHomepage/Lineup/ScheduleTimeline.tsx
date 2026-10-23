import {useEffect, useMemo, useRef, useState} from "react";
import {t} from "@lingui/macro";
import dayjs from "dayjs";
import {IconLayoutColumns, IconList, IconMapPin} from "@tabler/icons-react";
import {Event} from "../../../../types.ts";
import {EventLineup, EventSpace, LineupEntry, ScheduleItem} from "../../../../api/lineup.client.ts";
import {formatDateWithLocale, getSafeLocale} from "../../../../utilites/dates.ts";
import {getClientLocale, toDayjsLocale} from "../../../../locales.ts";
import {initialsFor, profilePhoto, scheduleItemTitle} from "../../../../utilites/lineup.ts";
import {assignScheduleLanes, groupScheduleByDay} from "../../../../utilites/lineupSchedule.ts";
import {UserGeneratedContent} from "../../../common/UserGeneratedContent";
import {HomepageSectionClasses} from "./index.tsx";
import {sizedImageUrl} from "../../../../utilites/imageTransform.ts";
import classes from "./Lineup.module.scss";

interface ScheduleTimelineProps {
    event: Event;
    lineup: EventLineup;
    entriesById: Map<number, LineupEntry>;
    title: string;
    sectionClasses: HomepageSectionClasses;
    onOpenEntry: (entryId: number) => void;
    onOpenItem: (itemId: number) => void;
}

type Layout = 'list' | 'grid';

const PX_PER_MINUTE = 1.6;
const MIN_BLOCK_HEIGHT = 28;
const BLOCK_PADDING_Y = 5;
const BLOCK_TITLE_LINE = 17;
const BLOCK_TIME_ROW = 16;
const MIN_HEIGHT_WITH_TIME = 56;
const ALL_SPACES = 'all';

const useNow = () => {
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => {
        setNow(Date.now());
        const interval = window.setInterval(() => setNow(Date.now()), 60_000);
        return () => window.clearInterval(interval);
    }, []);

    return now;
};

const formatScheduleWeekday = (utcDate: string, tz: string) =>
    dayjs.utc(utcDate).tz(tz).locale(toDayjsLocale(getSafeLocale(getClientLocale()))).format('dddd');

const isLive = (item: ScheduleItem, now: number | null) => now !== null
    && dayjs.utc(item.starts_at).valueOf() <= now
    && dayjs.utc(item.ends_at).valueOf() > now;

export const ScheduleTimeline = ({event, lineup, entriesById, title, sectionClasses, onOpenEntry, onOpenItem}: ScheduleTimelineProps) => {
    const tz = event.timezone;
    const now = useNow();
    const days = useMemo(() => groupScheduleByDay(lineup.schedule_items, tz), [lineup.schedule_items, tz]);
    const [selectedDay, setSelectedDay] = useState(days[0]?.key);
    const [layout, setLayout] = useState<Layout>('list');
    const [spaceFilter, setSpaceFilter] = useState<string>(ALL_SPACES);

    const clockReady = now !== null;

    useEffect(() => {
        if (!clockReady) {
            return;
        }
        const current = Date.now();
        const today = days.find(day => day.items.some(item => dayjs.utc(item.ends_at).valueOf() > current)
            && dayjs.utc(day.items[0].starts_at).valueOf() - current < 86_400_000);
        if (today) {
            setSelectedDay(today.key);
        }
    }, [clockReady, days]);

    const spacesById = useMemo(
        () => new Map<number, EventSpace>(lineup.spaces.map(space => [space.id, space])),
        [lineup.spaces],
    );
    const spaceIndex = useMemo(
        () => new Map<number, number>(lineup.spaces.map((space, index) => [space.id, index])),
        [lineup.spaces],
    );

    const day = days.find(candidate => candidate.key === selectedDay) ?? days[0];
    const usedSpaceIds = new Set(lineup.schedule_items.map(item => item.event_space_id).filter(Boolean));
    const usedSpaces = lineup.spaces.filter(space => usedSpaceIds.has(space.id));
    const canUseGrid = usedSpaces.length > 1;
    const activeLayout: Layout = canUseGrid ? layout : 'list';
    const filteredSpace = canUseGrid ? usedSpaces.find(space => String(space.id) === spaceFilter) : undefined;
    const activeSpaceFilter = filteredSpace ? spaceFilter : ALL_SPACES;
    const dayItems = (day?.items ?? []).filter(item => activeSpaceFilter === ALL_SPACES || String(item.event_space_id) === activeSpaceFilter);
    const filteredSpaceName = filteredSpace?.name;

    return (
        <>
            <div className={`${sectionClasses.sectionHeader} ${classes.scheduleHeader}`}>
                <h2 className={sectionClasses.sectionTitle}>{title}</h2>
                {canUseGrid && (
                    <div className={classes.layoutToggle} role="group" aria-label={t`Layout`}>
                        <button type="button" aria-pressed={activeLayout === 'list'} onClick={() => setLayout('list')}>
                            <IconList size={16}/>{t`List`}
                        </button>
                        <button type="button" aria-pressed={activeLayout === 'grid'} onClick={() => setLayout('grid')}>
                            <IconLayoutColumns size={16}/>{t`Stages`}
                        </button>
                    </div>
                )}
            </div>

            {days.length > 1 && (
                <div className={classes.dayTabs} role="group" aria-label={t`Days`}>
                    {days.map(candidate => {
                        const anchor = candidate.items[0].starts_at;
                        return (
                            <button
                                key={candidate.key}
                                type="button"
                                aria-pressed={candidate.key === day?.key}
                                className={classes.dayTab}
                                onClick={() => setSelectedDay(candidate.key)}
                            >
                                <span className={classes.dayTabName}>{formatScheduleWeekday(anchor, tz)}</span>
                                <span className={classes.dayTabDate}>
                                    {formatDateWithLocale(anchor, 'dayOfMonth', tz)} {formatDateWithLocale(anchor, 'monthShort', tz)}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            {activeLayout === 'list' && usedSpaces.length > 1 && (
                <div className={classes.spaceFilter}>
                    {[{id: ALL_SPACES, name: t`All stages`}, ...usedSpaces.map(space => ({id: String(space.id), name: space.name}))].map(option => (
                        <button
                            key={option.id}
                            type="button"
                            className={classes.spaceFilterChip}
                            aria-pressed={activeSpaceFilter === option.id}
                            onClick={() => setSpaceFilter(option.id)}
                        >
                            {option.id !== ALL_SPACES && (
                                <span className={classes.spaceDot} data-space-index={(spaceIndex.get(Number(option.id)) ?? 0) % 6}/>
                            )}
                            {option.name}
                        </button>
                    ))}
                </div>
            )}

            {activeLayout === 'list' && dayItems.length === 0 && filteredSpaceName && (
                <p className={classes.agendaEmpty}>{t`Nothing is scheduled on ${filteredSpaceName} this day.`}</p>
            )}

            {activeLayout === 'list'
                ? (
                    <ol className={classes.agenda}>
                        {dayItems.map(item => (
                            <AgendaRow
                                key={item.id}
                                item={item}
                                tz={tz}
                                live={isLive(item, now)}
                                space={item.event_space_id ? spacesById.get(item.event_space_id) : undefined}
                                spaceColorIndex={item.event_space_id ? (spaceIndex.get(item.event_space_id) ?? 0) % 6 : undefined}
                                entriesById={entriesById}
                                onOpenEntry={onOpenEntry}
                                onOpenItem={() => onOpenItem(item.id)}
                            />
                        ))}
                    </ol>
                )
                : (
                    <StageGrid
                        items={day?.items ?? []}
                        spaces={usedSpaces}
                        tz={tz}
                        now={now}
                        spaceIndex={spaceIndex}
                        entriesById={entriesById}
                        onOpenItem={onOpenItem}
                    />
                )}
        </>
    );
};

interface AgendaRowProps {
    item: ScheduleItem;
    tz: string;
    live: boolean;
    space?: EventSpace;
    spaceColorIndex?: number;
    entriesById: Map<number, LineupEntry>;
    onOpenEntry: (entryId: number) => void;
    onOpenItem: () => void;
}

const AgendaRow = ({item, tz, live, space, spaceColorIndex, entriesById, onOpenEntry, onOpenItem}: AgendaRowProps) => {
    const [expanded, setExpanded] = useState(false);
    const [overflowing, setOverflowing] = useState(false);
    const descriptionRef = useRef<HTMLDivElement>(null);
    const performers = item.lineup_entry_ids
        .map(id => entriesById.get(id))
        .filter((entry): entry is LineupEntry => !!entry);
    const title = scheduleItemTitle(item, entriesById);

    useEffect(() => {
        const element = descriptionRef.current;
        if (!element) {
            return;
        }
        const measure = () => setOverflowing(element.scrollHeight > element.clientHeight + 1);
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(element);
        return () => observer.disconnect();
    }, [item.description]);

    return (
        <li id={`session-${item.id}`} className={classes.agendaRow} data-live={live || undefined} data-testid="event-schedule-item">
            <div className={classes.agendaTime}>
                <span className={classes.agendaStart}>{formatDateWithLocale(item.starts_at, 'timeOnly', tz)}</span>
                <span className={classes.agendaEnd}>{formatDateWithLocale(item.ends_at, 'timeOnly', tz)}</span>
            </div>
            <div className={classes.agendaBody}>
                <div className={classes.agendaTitleRow}>
                    <button type="button" className={classes.agendaTitleButton} onClick={onOpenItem}>
                        {title}
                    </button>
                    {live && <span className={classes.livePill}>{t`On now`}</span>}
                </div>

                {(space || (item.title && performers.length > 0)) && (
                    <div className={classes.agendaMeta}>
                        {space && (
                            <span className={classes.agendaSpace}>
                                <span className={classes.spaceDot} data-space-index={spaceColorIndex}/>
                                {space.name}
                                {space.venue?.name && (
                                    <span className={classes.agendaVenue}><IconMapPin size={12}/>{space.venue.name}</span>
                                )}
                            </span>
                        )}
                        {item.title && performers.map(entry => (
                            <button key={entry.id} type="button" className={classes.performerChip} onClick={() => onOpenEntry(entry.id)}>
                                <PerformerAvatar entry={entry}/>
                                {entry.profile.name}
                            </button>
                        ))}
                    </div>
                )}

                {item.description && (
                    <div ref={descriptionRef} className={classes.agendaDescription} data-expanded={expanded || undefined}>
                        <UserGeneratedContent html={item.description}/>
                        {overflowing && !expanded && (
                            <button type="button" className={classes.moreButton} onClick={() => setExpanded(true)}>
                                {t`More`}
                            </button>
                        )}
                    </div>
                )}
            </div>
            {performers.length > 0 && !item.title && (
                <button type="button" className={classes.agendaAvatars} onClick={onOpenItem} tabIndex={-1} aria-hidden>
                    {performers.slice(0, 3).map(entry => <PerformerAvatar key={entry.id} entry={entry} size="lg"/>)}
                </button>
            )}
        </li>
    );
};

export const PerformerAvatar = ({entry, size = 'sm'}: { entry: LineupEntry, size?: 'sm' | 'lg' }) => {
    const photo = profilePhoto(entry.profile);

    return (
        <span className={classes.avatar} data-size={size} style={photo?.avg_colour ? {backgroundColor: photo.avg_colour} : undefined} aria-hidden>
            {photo ? <img src={sizedImageUrl(photo.url, 120)} alt="" loading="lazy"/> : initialsFor(entry.profile.name)}
        </span>
    );
};

interface StageGridProps {
    items: ScheduleItem[];
    spaces: EventSpace[];
    tz: string;
    now: number | null;
    spaceIndex: Map<number, number>;
    entriesById: Map<number, LineupEntry>;
    onOpenItem: (itemId: number) => void;
}

const StageGrid = ({items, spaces, tz, now, spaceIndex, entriesById, onOpenItem}: StageGridProps) => {
    const hasUnassigned = items.some(item => !item.event_space_id);
    const columns = [
        ...spaces.map(space => ({key: String(space.id), name: space.name, colorIndex: (spaceIndex.get(space.id) ?? 0) % 6})),
        ...(hasUnassigned ? [{key: 'none', name: t`Everywhere`, colorIndex: undefined}] : []),
    ];

    const rangeStart = dayjs.utc(Math.min(...items.map(item => dayjs.utc(item.starts_at).valueOf()))).tz(tz).startOf('hour');
    const rangeEndMs = Math.max(...items.map(item => dayjs.utc(item.ends_at).valueOf()));
    const totalMinutes = Math.max(60, Math.ceil((rangeEndMs - rangeStart.valueOf()) / 60_000 / 60) * 60);
    const hours = Array.from({length: totalMinutes / 60 + 1}, (_, index) => rangeStart.add(index, 'hour'));
    const offset = (utc: string) => (dayjs.utc(utc).valueOf() - rangeStart.valueOf()) / 60_000 * PX_PER_MINUTE;
    const nowOffset = now !== null ? (now - rangeStart.valueOf()) / 60_000 * PX_PER_MINUTE : null;
    const showNow = nowOffset !== null && nowOffset >= 0 && nowOffset <= totalMinutes * PX_PER_MINUTE;

    return (
        <div className={classes.stageGridScroller}>
            <div
                className={classes.stageGrid}
                style={{'--stage-count': columns.length, '--grid-height': `${totalMinutes * PX_PER_MINUTE}px`} as React.CSSProperties}
            >
                <div className={classes.stageGridCorner}/>
                {columns.map(column => (
                    <div key={column.key} className={classes.stageHeading}>
                        {column.colorIndex !== undefined && <span className={classes.spaceDot} data-space-index={column.colorIndex}/>}
                        {column.name}
                    </div>
                ))}

                <div className={classes.timeGutter} aria-hidden>
                    {hours.map((hour, index) => (
                        <span key={index} style={{top: index * 60 * PX_PER_MINUTE}}>
                            {formatDateWithLocale(hour.utc().format(), 'timeOnly', tz)}
                        </span>
                    ))}
                </div>

                {columns.map(column => {
                    const blocks = items
                        .filter(item => String(item.event_space_id ?? 'none') === column.key)
                        .map(item => {
                            const top = offset(item.starts_at);
                            return {item, top, bottom: top + Math.max(offset(item.ends_at) - top, MIN_BLOCK_HEIGHT)};
                        });
                    const lanes = assignScheduleLanes(blocks.map(({item, top, bottom}) => ({id: item.id, top, bottom})));

                    return (
                        <div key={column.key} className={classes.stageColumn}>
                            {blocks.map(({item, top, bottom}) => {
                                const height = bottom - top;
                                const {lane, lanes: laneCount} = lanes.get(item.id) ?? {lane: 0, lanes: 1};
                                const showTime = height >= MIN_HEIGHT_WITH_TIME;
                                const titleLines = Math.max(1, Math.floor((height - 2 * BLOCK_PADDING_Y - (showTime ? BLOCK_TIME_ROW : 0)) / BLOCK_TITLE_LINE));

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        className={classes.stageBlock}
                                        data-space-index={column.colorIndex}
                                        data-live={isLive(item, now) || undefined}
                                        data-testid="event-stage-block"
                                        style={{
                                            top,
                                            height,
                                            left: `${lane / laneCount * 100}%`,
                                            width: `${100 / laneCount}%`,
                                            '--title-lines': titleLines,
                                        } as React.CSSProperties}
                                        onClick={() => onOpenItem(item.id)}
                                    >
                                        <span className={classes.stageBlockTitle}>{scheduleItemTitle(item, entriesById)}</span>
                                        <span className={classes.visuallyHidden}>{column.name}</span>
                                        <span className={showTime ? classes.stageBlockTime : classes.visuallyHidden}>
                                            {formatDateWithLocale(item.starts_at, 'timeOnly', tz)} – {formatDateWithLocale(item.ends_at, 'timeOnly', tz)}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    );
                })}

                {showNow && <div className={classes.nowLine} style={{marginTop: nowOffset!}}/>}
            </div>
        </div>
    );
};
