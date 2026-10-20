import {useState} from "react";
import {useParams, useSearchParams} from "react-router";
import {t} from "@lingui/macro";
import {Button, Menu, SegmentedControl, Skeleton} from "@mantine/core";
import {
    IconCalendarPlus,
    IconCheck,
    IconChevronDown,
    IconDownload,
    IconExternalLink,
    IconFileSpreadsheet,
    IconLayoutGrid,
    IconList,
    IconMapPinPlus,
    IconPrinter,
    IconTimeline,
    IconUpload,
} from "@tabler/icons-react";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventLineup} from "../../../../queries/useGetEventLineup.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {downloadRunSheet, getLineupTerminology, toEventLocal} from "../../../../utilites/lineup.ts";
import {LOCAL_DATETIME_FORMAT, scheduleDayKey} from "../../../../utilites/lineupSchedule.ts";
import dayjs from "dayjs";
import {eventHomepagePath} from "../../../../utilites/urlHelper.ts";
import {Event} from "../../../../types.ts";
import {EventLineup, EventSpace, ScheduleItem} from "../../../../api/lineup.client.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {LineupStats} from "./LineupStats.tsx";
import {PeopleRail} from "./PeopleRail.tsx";
import {LineupPreview} from "./LineupPreview.tsx";
import {AgendaList} from "./AgendaList.tsx";
import {initialScheduleDate, TimetablePanel} from "./ScheduleBuilder";
import {ScheduleItemDraft, ScheduleItemModal} from "./ScheduleBuilder/ScheduleItemModal.tsx";
import {SpaceModal} from "./ScheduleBuilder/SpaceModal.tsx";
import {ProfileDrawer} from "./ProfileDrawer.tsx";
import {ImportLineupModal} from "./ImportLineupModal.tsx";
import {useOccurrenceDays} from "./useOccurrenceDays.ts";
import {localScheduleDayKey} from "./ScheduleBuilder/scheduleDays.ts";
import classes from "./Lineup.module.scss";

type View = 'lineup' | 'timetable' | 'agenda';

const VIEWS: View[] = ['lineup', 'timetable', 'agenda'];

const Lineup = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {data: lineup} = useGetEventLineup(eventId);

    return (
        <PageBody>
            <div className={classes.page}>
                <div className={classes.pageHeader}>
                    <PageTitle subheading={t`Everyone appearing at this event, where they are and when. Shown on your event page as soon as you add them.`}>
                        {t`Lineup & Schedule`}
                    </PageTitle>
                    {event && (
                        <div className={classes.pageActions}>
                            {lineup && <ImportButton event={event} lineup={lineup}/>}
                            <ExportMenu event={event}/>
                            <Button
                                component="a"
                                href={eventHomepagePath(event)}
                                target="_blank"
                                variant="default"
                                size="sm"
                                leftSection={<IconExternalLink size={16}/>}
                            >
                                {t`View on event page`}
                            </Button>
                        </div>
                    )}
                </div>

                {event && lineup
                    ? <LineupWorkspace key={event.id} event={event} lineup={lineup}/>
                    : (
                        <div className={classes.loading}>
                            <Skeleton height={84} radius="md"/>
                            <Skeleton height={420} radius="md"/>
                        </div>
                    )}
            </div>
        </PageBody>
    );
};

const ImportButton = ({event, lineup}: { event: Event, lineup: EventLineup }) => {
    const {isReadOnly, canManageOrganizer} = useAccess();
    const [opened, setOpened] = useState(false);

    if (isReadOnly || !canManageOrganizer(event.organizer_id)) {
        return null;
    }

    return (
        <>
            <Button
                variant="default"
                size="sm"
                leftSection={<IconUpload size={16}/>}
                onClick={() => setOpened(true)}
                data-testid="lineup-import-button"
            >
                {t`Import`}
            </Button>
            {opened && <ImportLineupModal event={event} lineup={lineup} onClose={() => setOpened(false)}/>}
        </>
    );
};

const ExportMenu = ({event}: { event: Event }) => {
    const [exporting, setExporting] = useState(false);

    const downloadSpreadsheet = async () => {
        setExporting(true);
        try {
            await downloadRunSheet(event);
        } catch {
            showError(t`Could not export the run sheet. Please try again.`);
        } finally {
            setExporting(false);
        }
    };

    return (
        <Menu position="bottom-end" shadow="md" width={240}>
            <Menu.Target>
                <Button
                    variant="default"
                    size="sm"
                    loading={exporting}
                    leftSection={<IconDownload size={16}/>}
                    rightSection={<IconChevronDown size={14}/>}
                    data-testid="lineup-export-button"
                >
                    {t`Export`}
                </Button>
            </Menu.Target>
            <Menu.Dropdown>
                <Menu.Item leftSection={<IconFileSpreadsheet size={16}/>} onClick={downloadSpreadsheet} data-testid="lineup-export-xlsx">
                    {t`Download spreadsheet (.xlsx)`}
                </Menu.Item>
                <Menu.Item
                    leftSection={<IconPrinter size={16}/>}
                    component="a"
                    href={`/manage/event/${event.id}/lineup/print`}
                    target="_blank"
                    data-testid="lineup-export-print"
                >
                    {t`Printable run sheet`}
                </Menu.Item>
            </Menu.Dropdown>
        </Menu>
    );
};

const LineupWorkspace = ({event, lineup}: { event: Event, lineup: EventLineup }) => {
    const {isReadOnly} = useAccess();
    const [searchParams, setSearchParams] = useSearchParams();
    const terminology = getLineupTerminology(event.category);
    const requestedView = searchParams.get('view') as View | null;
    const view: View = requestedView && VIEWS.includes(requestedView) ? requestedView : 'lineup';
    const [drawerEntryId, setDrawerEntryId] = useState<number | null>(null);
    const [slotModal, setSlotModal] = useState<{ draft?: ScheduleItemDraft, item?: ScheduleItem } | null>(null);
    const [spaceModal, setSpaceModal] = useState<{ space?: EventSpace } | null>(null);
    const [date, setDate] = useState(() => initialScheduleDate(event, lineup));
    const occurrenceDays = useOccurrenceDays(event);

    const drawerEntry = lineup.entries.find(entry => entry.id === drawerEntryId);
    const drawerSlotCount = lineup.schedule_items.filter(item => drawerEntryId && item.lineup_entry_ids.includes(drawerEntryId)).length;

    const setView = (next: string) => setSearchParams(params => {
        params.set('view', next);
        return params;
    }, {replace: true});

    const nextFreeSlot = () => {
        const lastEnd = lineup.schedule_items
            .filter(item => scheduleDayKey(item.starts_at, event.timezone) === date)
            .map(item => toEventLocal(item.ends_at, event.timezone))
            .sort((a, b) => b.valueOf() - a.valueOf())[0];
        const start = lastEnd ?? dayjs(`${date}T19:00`);
        return {
            start: start.format(LOCAL_DATETIME_FORMAT),
            end: start.add(1, 'hour').format(LOCAL_DATETIME_FORMAT),
        };
    };

    const openNewSlot = (draft?: Partial<ScheduleItemDraft>) => setSlotModal({
        draft: {
            ...nextFreeSlot(),
            spaceId: lineup.spaces[0]?.id ?? null,
            entryIds: [],
            ...draft,
        },
    });

    return (
        <>
            <LineupStats event={event} lineup={lineup} lineupLabel={terminology.lineup} occurrenceDays={occurrenceDays}/>

            <div className={classes.workspace}>
                <PeopleRail
                    event={event}
                    lineup={lineup}
                    title={terminology.lineup}
                    selectedEntryId={drawerEntryId}
                    onOpenEntry={setDrawerEntryId}
                />

                <section className={classes.panel}>
                    <div className={classes.panelHeader}>
                        <SegmentedControl
                            value={view}
                            onChange={setView}
                            aria-label={t`View`}
                            data-testid="lineup-view-switch"
                            data={[
                                {value: 'lineup', label: <span className={classes.viewLabel}><IconLayoutGrid size={14}/>{t`Preview`}</span>},
                                {value: 'timetable', label: <span className={classes.viewLabel}><IconTimeline size={14}/>{terminology.schedule}</span>},
                                {value: 'agenda', label: <span className={classes.viewLabel}><IconList size={14}/>{t`List`}</span>},
                            ]}
                        />
                        {view !== 'lineup' && !isReadOnly && (
                            <div className={classes.panelActions}>
                                <Button
                                    variant="default"
                                    size="xs"
                                    leftSection={<IconMapPinPlus size={14}/>}
                                    onClick={() => setSpaceModal({})}
                                    data-testid="lineup-add-space-button"
                                >
                                    {t`Add stage or room`}
                                </Button>
                                <Button
                                    size="xs"
                                    leftSection={<IconCalendarPlus size={14}/>}
                                    onClick={() => openNewSlot()}
                                    data-testid="lineup-add-slot-button"
                                >
                                    {t`Add time slot`}
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className={classes.panelBody}>
                        {view === 'lineup' && (lineup.entries.length > 0
                            ? <LineupPreview event={event} lineup={lineup} onOpenEntry={setDrawerEntryId}/>
                            : isReadOnly
                                ? <div className={classes.panelEmpty}><p>{t`Nobody has been added to this event's lineup yet.`}</p></div>
                                : (
                                <SetupGuide
                                    event={event}
                                    lineup={lineup}
                                    lineupLabel={terminology.lineup}
                                    onAddStage={() => setSpaceModal({})}
                                    onSchedule={() => setView('timetable')}
                                />
                            ))}

                        {view === 'timetable' && (
                            <TimetablePanel
                                event={event}
                                lineup={lineup}
                                date={date}
                                occurrenceDays={occurrenceDays}
                                onDateChange={setDate}
                                onCreate={openNewSlot}
                                onEdit={item => setSlotModal({item})}
                                onEditSpace={space => setSpaceModal({space})}
                            />
                        )}

                        {view === 'agenda' && (
                            <AgendaList
                                event={event}
                                lineup={lineup}
                                onEdit={item => setSlotModal({item})}
                                onAdd={() => openNewSlot()}
                            />
                        )}
                    </div>
                </section>
            </div>

            {drawerEntry && (
                <ProfileDrawer
                    organizerId={event.organizer_id!}
                    eventId={event.id!}
                    entry={drawerEntry}
                    slotCount={drawerSlotCount}
                    onClose={() => setDrawerEntryId(null)}
                />
            )}

            {slotModal && (
                <ScheduleItemModal
                    event={event}
                    lineup={lineup}
                    item={slotModal.item}
                    draft={slotModal.draft}
                    occurrenceDays={occurrenceDays}
                    onSaved={start => setDate(localScheduleDayKey(start))}
                    onClose={() => setSlotModal(null)}
                />
            )}

            {spaceModal && (
                <SpaceModal
                    event={event}
                    lineup={lineup}
                    space={spaceModal.space}
                    onClose={() => setSpaceModal(null)}
                />
            )}
        </>
    );
};

interface SetupGuideProps {
    event: Event;
    lineup: EventLineup;
    lineupLabel: string;
    onAddStage: () => void;
    onSchedule: () => void;
}

const SetupGuide = ({event, lineup, lineupLabel, onAddStage, onSchedule}: SetupGuideProps) => {
    const steps = [
        {
            done: lineup.entries.length > 0,
            title: t`Add people`,
            body: t`Search your saved profiles or type a new name in the ${lineupLabel} panel. Add a photo, bio and links to each.`,
            action: null,
        },
        {
            done: lineup.spaces.length > 0,
            title: t`Add stages or rooms`,
            body: t`Optional. Useful when things happen in more than one place at the same time.`,
            action: <Button size="xs" variant="light" onClick={onAddStage}>{t`Add a stage or room`}</Button>,
        },
        {
            done: lineup.schedule_items.length > 0,
            title: t`Give everyone a time`,
            body: t`Drag people onto the timetable or add time slots with a form.`,
            action: <Button size="xs" variant="light" onClick={onSchedule}>{t`Open timetable`}</Button>,
        },
        {
            done: false,
            title: t`Check your event page`,
            body: t`The lineup and schedule follow your event's colours and fonts automatically.`,
            action: (
                <Button size="xs" variant="subtle" component="a" href={eventHomepagePath(event)} target="_blank" rightSection={<IconExternalLink size={12}/>}>
                    {t`Open event page`}
                </Button>
            ),
        },
    ];

    return (
        <div className={classes.setupGuide}>
            <h3>{t`Build your lineup`}</h3>
            <p className={classes.summaryMuted}>{t`Speakers for a conference, artists for a festival, hosts for a workshop. It all works the same way.`}</p>
            <ol className={classes.setupSteps}>
                {steps.map((step, index) => (
                    <li key={step.title} data-done={step.done || undefined}>
                        <span className={classes.setupStepNumber}>{step.done ? <IconCheck size={14}/> : index + 1}</span>
                        <div className={classes.setupStepBody}>
                            <strong>{step.title}</strong>
                            <span>{step.body}</span>
                        </div>
                        {step.action}
                    </li>
                ))}
            </ol>
        </div>
    );
};

export default Lineup;
