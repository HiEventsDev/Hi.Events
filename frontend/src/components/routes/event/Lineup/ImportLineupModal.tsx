import {ReactNode, useCallback, useEffect, useId, useMemo, useRef, useState} from "react";
import {plural, t} from "@lingui/macro";
import {Button, Loader, SegmentedControl, Switch} from "@mantine/core";
import {Dropzone} from "@mantine/dropzone";
import {useMediaQuery} from "@mantine/hooks";
import {
    IconAlertTriangle,
    IconCalendarTime,
    IconClipboardText,
    IconClock,
    IconFileSpreadsheet,
    IconMapPin,
    IconUpload,
    IconUsers,
    IconX,
} from "@tabler/icons-react";
import {isAxiosError} from "axios";
import {Modal, ModalFooter} from "../../../common/Modal";
import {Event} from "../../../../types.ts";
import {EventLineup, ImportLineupResult} from "../../../../api/lineup.client.ts";
import {useGetProfiles} from "../../../../queries/useGetProfiles.ts";
import {useImportLineup} from "../../../../mutations/useImportLineup.ts";
import {billingTierLabel, downloadRunSheet, toEventLocal} from "../../../../utilites/lineup.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {
    buildImportPlan,
    ColumnAliases,
    ExistingPerson,
    htmlToPlainKey,
    ImportContext,
    ImportPlan,
    importPayload,
    nameKey,
    PEOPLE_COLUMN_ALIASES,
    PeopleColumn,
    peopleToAdd,
    PersonImportRow,
    RowError,
    RowStatus,
    SCHEDULE_COLUMN_ALIASES,
    ScheduleColumn,
    SlotImportRow,
    slotKey,
} from "../../../../utilites/lineupImport.ts";
import {parseDelimitedText, readSpreadsheetFile, SpreadsheetReadError, SpreadsheetSheet} from "../../../../utilites/spreadsheetReader.ts";
import {LOCAL_DATETIME_FORMAT} from "../../../../utilites/lineupSchedule.ts";
import {useOccurrenceDays} from "./useOccurrenceDays.ts";
import {localScheduleDayKey} from "./ScheduleBuilder/scheduleDays.ts";
import classes from "./ImportLineupModal.module.scss";

const ACCEPTED_TYPES = {
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
    'text/csv': ['.csv'],
    'application/vnd.ms-excel': ['.csv'],
    'text/tab-separated-values': ['.tsv'],
    'text/plain': ['.csv', '.tsv', '.txt'],
};

const MAX_FILE_BYTES = 10 * 1024 * 1024;

type Tab = 'people' | 'slots';

interface Source {
    label: string;
    sheets: SpreadsheetSheet[];
}

const rowKey = (row: { sheet: string, row: number }) => `${row.sheet}:${row.row}`;

const withLocalAliases = <T extends string>(aliases: ColumnAliases<T>, local: Record<T, string>): ColumnAliases<T> =>
    Object.fromEntries(Object.entries(aliases).map(([column, list]) => [column, [...(list as string[]), local[column as T]]])) as ColumnAliases<T>;

const peopleColumnLabels = (): Record<PeopleColumn, string> => ({
    name: t({message: 'Name', context: 'Lineup spreadsheet column'}),
    billing: t({message: 'Billing', context: 'Lineup spreadsheet column'}),
    role: t({message: 'Role', context: 'Lineup spreadsheet column'}),
    tagline: t({message: 'Tagline', context: 'Lineup spreadsheet column'}),
    bio: t({message: 'Bio', context: 'Lineup spreadsheet column'}),
    links: t({message: 'Links', context: 'Lineup spreadsheet column'}),
    hidden: t({message: 'Hidden', context: 'Lineup spreadsheet column'}),
});

const scheduleColumnLabels = (): Record<ScheduleColumn, string> => ({
    date: t({message: 'Date', context: 'Lineup spreadsheet column'}),
    start: t({message: 'Start', context: 'Lineup spreadsheet column'}),
    end: t({message: 'End', context: 'Lineup spreadsheet column'}),
    duration: t({message: 'Duration (min)', context: 'Lineup spreadsheet column'}),
    stage: t({message: 'Stage', context: 'Lineup spreadsheet column'}),
    title: t({message: 'Title', context: 'Lineup spreadsheet column'}),
    people: t({message: 'People', context: 'Lineup spreadsheet column'}),
    description: t({message: 'Description', context: 'Lineup spreadsheet column'}),
});

const errorMessage = (error: RowError): string => {
    switch (error) {
        case 'missing_name':
            return t`Name is missing`;
        case 'missing_start':
            return t`Start time is missing`;
        case 'bad_date':
            return t`Date not recognised. Use a format like 2027-07-16.`;
        case 'bad_time':
            return t`Time not recognised. Use a format like 19:30 or 7:30 PM.`;
        case 'missing_end':
            return t`Add an end time or a duration`;
        case 'end_before_start':
            return t`Ends before it starts`;
        case 'empty_slot':
            return t`Needs a title or at least one person`;
        case 'too_long':
            return t`Some text is too long. Names and titles can be up to 255 characters, roles and stages up to 100.`;
        case 'too_many_people':
            return t`A time slot can have up to 50 people`;
        case 'too_many_rows':
            return t`Too many rows in one import`;
    }
};

const statusLabel = (status: RowStatus, kind: Tab): string => {
    switch (status) {
        case 'new':
            return kind === 'people' ? t`New` : t`Add`;
        case 'add':
            return t`Saved profile`;
        case 'update':
            return t`Update`;
        case 'unchanged':
            return t`No changes`;
        case 'duplicate':
            return kind === 'people' ? t`Repeated` : t`Already scheduled`;
        case 'error':
            return t`Can't import`;
    }
};

const readError = (error: unknown): string => {
    if (error instanceof SpreadsheetReadError && error.code === 'unsupported_browser') {
        return t`This browser can't open .xlsx files. Save the file as CSV and try again.`;
    }
    if (error instanceof SpreadsheetReadError && error.code === 'unsupported_type') {
        return t`Choose an .xlsx or .csv file.`;
    }
    return t`We couldn't read that file. Check it's a valid .xlsx or .csv file.`;
};

const looksTabular = (text: string) => text.includes('\t') || text.includes(',') || text.includes(';');

const resultMessage = (result: ImportLineupResult): string => {
    const peopleAdded = result.people_added;
    const slotsAdded = result.slots_created;
    const people = plural(peopleAdded, {one: '# person added', other: '# people added'});
    const slots = plural(slotsAdded, {one: '# time slot added', other: '# time slots added'});
    const updated = result.people_updated;

    return updated > 0
        ? t`${people}, ${slots}, ${updated} updated`
        : t`${people}, ${slots}`;
};

interface ImportLineupModalProps {
    event: Event;
    lineup: EventLineup;
    onClose: () => void;
}

export const ImportLineupModal = ({event, lineup, onClose}: ImportLineupModalProps) => {
    // eslint-disable-next-line lingui/no-unlocalized-strings
    const isMobile = useMediaQuery('(max-width: 48em)');
    const profilesQuery = useGetProfiles(event.organizer_id);
    const occurrenceDays = useOccurrenceDays(event);
    const importMutation = useImportLineup();
    const [source, setSource] = useState<Source | null>(null);
    const [reading, setReading] = useState(false);
    const [readFailure, setReadFailure] = useState<string | null>(null);
    const [tab, setTab] = useState<Tab>('slots');
    const [problemsOnly, setProblemsOnly] = useState(false);
    const [dayFirst, setDayFirst] = useState(() => !/^(en-US|en-CA|zh|ja|ko)/i.test(typeof navigator === 'undefined' ? '' : navigator.language));
    const [serverErrors, setServerErrors] = useState<Map<string, string>>(new Map());

    const context = useMemo<ImportContext>(() => {
        const entriesById = new Map(lineup.entries.map(entry => [entry.id, entry]));
        const entriesByProfileId = new Map(lineup.entries.map(entry => [entry.profile.id, entry]));
        const spacesById = new Map(lineup.spaces.map(space => [space.id, space.name]));
        const people = new Map<string, ExistingPerson>();

        [...(profilesQuery.data ?? [])]
            .sort((a, b) => a.id - b.id)
            .forEach(profile => {
                const key = nameKey(profile.name);
                const entry = entriesByProfileId.get(profile.id);
                if (people.has(key) && !entry) {
                    return;
                }
                people.set(key, {
                    tagline: profile.tagline,
                    bioText: htmlToPlainKey(profile.bio),
                    links: profile.links.map(link => link.url),
                    billingTier: entry?.billing_tier,
                    roleLabel: entry?.role_label,
                    isHidden: entry ? !!entry.is_hidden : undefined,
                });
            });

        return {
            profiles: people,
            lineupNames: new Set(lineup.entries.map(entry => nameKey(entry.profile.name))),
            stageNames: new Set(lineup.spaces.map(space => nameKey(space.name))),
            existingSlotKeys: new Set(lineup.schedule_items.map(item => slotKey(
                toEventLocal(item.starts_at, event.timezone).format(LOCAL_DATETIME_FORMAT),
                toEventLocal(item.ends_at, event.timezone).format(LOCAL_DATETIME_FORMAT),
                item.event_space_id ? spacesById.get(item.event_space_id) ?? null : null,
                item.title,
                item.lineup_entry_ids.map(id => entriesById.get(id)?.profile.name ?? ''),
            ))),
            billingLabels: {
                [billingTierLabel('HEADLINER')]: 'HEADLINER',
                [billingTierLabel('FEATURED')]: 'FEATURED',
                [billingTierLabel('STANDARD')]: 'STANDARD',
            },
            yesWords: [t`Yes`],
            dayFirst,
            peopleAliases: withLocalAliases(PEOPLE_COLUMN_ALIASES, peopleColumnLabels()),
            scheduleAliases: withLocalAliases(SCHEDULE_COLUMN_ALIASES, scheduleColumnLabels()),
        };
    }, [lineup, profilesQuery.data, event.timezone, dayFirst]);

    const plan = useMemo(() => source ? buildImportPlan(source.sheets, context) : null, [source, context]);

    const showSource = useCallback((next: Source) => {
        setSource(next);
        setTab('slots');
        setProblemsOnly(false);
        setServerErrors(new Map());
        setReadFailure(null);
    }, []);

    const showPastedText = useCallback((text: string) => {
        if (!looksTabular(text)) {
            setReadFailure(t`Copy some rows from a spreadsheet, including the header row, then paste them here.`);
            return;
        }
        showSource({label: t`Pasted rows`, sheets: [{name: t`Pasted rows`, rows: parseDelimitedText(text)}]});
    }, [showSource]);

    useEffect(() => {
        if (source) {
            return;
        }
        const handlePaste = (pasteEvent: ClipboardEvent) => {
            const text = pasteEvent.clipboardData?.getData('text/plain');
            if (!text) {
                return;
            }
            pasteEvent.preventDefault();
            showPastedText(text);
        };
        document.addEventListener('paste', handlePaste);
        return () => document.removeEventListener('paste', handlePaste);
    }, [source, showPastedText]);

    const openFile = async (file: File) => {
        setReading(true);
        setReadFailure(null);
        try {
            showSource({label: file.name, sheets: await readSpreadsheetFile(file)});
        } catch (error) {
            setReadFailure(readError(error));
        } finally {
            setReading(false);
        }
    };

    const pasteFromClipboard = async () => {
        try {
            showPastedText(await navigator.clipboard.readText());
        } catch {
            setReadFailure(t`We couldn't read your clipboard. Press Ctrl+V or ⌘+V to paste instead.`);
        }
    };

    const downloadTemplate = async () => {
        try {
            await downloadRunSheet(event);
        } catch {
            showError(t`Could not download the template. Please try again.`);
        }
    };

    const prepared = plan ? importPayload(plan) : null;
    const slotCount = prepared?.payload.slots.length ?? 0;
    const addCount = plan ? peopleToAdd(plan) : 0;
    const updateCount = plan ? plan.people.filter(person => person.status === 'update').length : 0;
    const nothingToImport = !prepared || (prepared.payload.people.length === 0 && slotCount === 0);
    const profilesReady = profilesQuery.isFetched;

    const submit = () => prepared && importMutation.mutate({eventId: event.id!, organizerId: event.organizer_id!, payload: prepared.payload}, {
        onSuccess: result => {
            showSuccess(resultMessage(result));
            onClose();
        },
        onError: error => {
            const errors = isAxiosError(error) ? error.response?.data?.errors as Record<string, string[]> | undefined : undefined;
            if (!errors) {
                showError(t`The import failed. Nothing was changed. Please try again.`);
                return;
            }
            const rejected = new Map<string, string>();
            Object.entries(errors).forEach(([field, messages]) => {
                const [group, index] = field.split('.');
                const row = group === 'people' ? prepared.rows.people[Number(index)] : prepared.rows.slots[Number(index)];
                if (row && !rejected.has(rowKey(row))) {
                    rejected.set(rowKey(row), messages[0]);
                }
            });
            if (rejected.size === 0) {
                showError(Object.values(errors)[0]?.[0] ?? t`The import failed. Nothing was changed. Please try again.`);
                return;
            }
            setServerErrors(rejected);
            setProblemsOnly(true);
            showError(t`Some rows were rejected. Nothing was changed.`);
        },
    });

    const footerSummary = () => {
        if (nothingToImport) {
            return t`Nothing new to import`;
        }
        const slots = plural(slotCount, {one: '# time slot', other: '# time slots'});
        const people = plural(addCount, {one: '# person to add', other: '# people to add'});
        return updateCount > 0 ? t`${slots}, ${people}, ${updateCount} to update` : t`${slots}, ${people}`;
    };

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Import lineup & schedule`}
            size={source ? 960 : 640}
            fullScreen={isMobile}
            classNames={{body: classes.modalBody}}
        >
            {!source
                ? (
                    <PickStep
                        reading={reading}
                        failure={readFailure}
                        onFile={openFile}
                        onPaste={typeof navigator !== 'undefined' && typeof navigator.clipboard?.readText === 'function' ? pasteFromClipboard : undefined}
                        onTemplate={downloadTemplate}
                    />
                )
                : plan && (
                    <PreviewStep
                        source={source}
                        plan={plan}
                        ready={profilesReady}
                        timezone={event.timezone}
                        occurrenceDays={occurrenceDays}
                        serverErrors={serverErrors}
                        dayFirst={dayFirst}
                        onDayFirstChange={setDayFirst}
                        tab={tab}
                        onTabChange={setTab}
                        problemsOnly={problemsOnly}
                        onProblemsOnlyChange={setProblemsOnly}
                        onReset={() => setSource(null)}
                    />
                )}

            {source && plan && (plan.people.length > 0 || plan.slots.length > 0) && (
                <ModalFooter
                    start={(
                        <span className={classes.footerSummary} data-testid="lineup-import-footer-summary">
                            {profilesReady ? footerSummary() : t`Checking your saved profiles…`}
                        </span>
                    )}
                >
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        onClick={submit}
                        loading={importMutation.isPending}
                        disabled={nothingToImport || !profilesReady || serverErrors.size > 0}
                        leftSection={<IconUpload size={16}/>}
                        data-testid="lineup-import-submit"
                    >
                        {t`Import`}
                    </Button>
                </ModalFooter>
            )}
        </Modal>
    );
};

interface PickStepProps {
    reading: boolean;
    failure: string | null;
    onFile: (file: File) => void;
    onPaste?: () => void;
    onTemplate: () => void;
}

const PickStep = ({reading, failure, onFile, onPaste, onTemplate}: PickStepProps) => {
    const scheduleColumns = Object.entries(scheduleColumnLabels())
        .filter(([column]) => column !== 'duration')
        .map(([, label]) => label);
    const peopleColumns = Object.values(peopleColumnLabels());

    return (
        <div className={classes.pick}>
            <Dropzone
                onDrop={files => files[0] && onFile(files[0])}
                onReject={() => showError(t`Choose an .xlsx or .csv file under 10 MB.`)}
                accept={ACCEPTED_TYPES}
                maxSize={MAX_FILE_BYTES}
                multiple={false}
                loading={reading}
                className={classes.dropzone}
                role="button"
                aria-label={t`Choose a spreadsheet to import`}
                data-testid="lineup-import-dropzone"
            >
                <div className={classes.dropzoneInner}>
                    <span className={classes.dropzoneIcon}>
                        <Dropzone.Accept><IconUpload size={26}/></Dropzone.Accept>
                        <Dropzone.Reject><IconX size={26}/></Dropzone.Reject>
                        <Dropzone.Idle><IconFileSpreadsheet size={26}/></Dropzone.Idle>
                    </span>
                    <strong>{t`Drop a spreadsheet here or click to choose`}</strong>
                    <span>{t`Excel (.xlsx) or CSV. You'll see exactly what will change before anything is saved.`}</span>
                </div>
            </Dropzone>

            {failure && (
                <div className={classes.failure} role="alert">
                    <IconAlertTriangle size={16}/>
                    {failure}
                </div>
            )}

            <div className={classes.pasteHint}>
                <IconClipboardText size={16}/>
                <span>{t`Or copy rows, headers included, from Google Sheets or Excel and paste them here.`}</span>
                {onPaste && (
                    <Button size="compact-sm" variant="light" onClick={onPaste} data-testid="lineup-import-paste-button">
                        {t`Paste`}
                    </Button>
                )}
            </div>

            <div className={classes.columns}>
                <ColumnList icon={<IconCalendarTime size={16}/>} title={t`Schedule sheet`} columns={scheduleColumns}
                            note={t`End can be after midnight. Separate several people with a semicolon.`}/>
                <ColumnList icon={<IconUsers size={16}/>} title={t`People sheet`} columns={peopleColumns}
                            note={t`People are matched by name, so existing profiles are updated rather than duplicated.`}/>
            </div>

            <button type="button" className={classes.templateLink} onClick={onTemplate}>
                <IconFileSpreadsheet size={16}/>
                {t`Download a template with your current lineup`}
            </button>
        </div>
    );
};

const ColumnList = ({icon, title, columns, note}: { icon: ReactNode, title: string, columns: string[], note: string }) => (
    <div className={classes.columnList}>
        <div className={classes.columnListTitle}>{icon}{title}</div>
        <div className={classes.columnChips}>
            {columns.map(column => <span key={column}>{column}</span>)}
        </div>
        <p>{note}</p>
    </div>
);

interface PreviewStepProps {
    source: Source;
    plan: ImportPlan;
    ready: boolean;
    timezone: string;
    occurrenceDays?: ReadonlySet<string>;
    serverErrors: Map<string, string>;
    dayFirst: boolean;
    onDayFirstChange: (value: boolean) => void;
    tab: Tab;
    onTabChange: (tab: Tab) => void;
    problemsOnly: boolean;
    onProblemsOnlyChange: (value: boolean) => void;
    onReset: () => void;
}

const PreviewStep = (props: PreviewStepProps) => {
    const {source, plan, ready, timezone, occurrenceDays, serverErrors, dayFirst, onDayFirstChange, tab, onTabChange, problemsOnly, onProblemsOnlyChange, onReset} = props;
    const summaryRef = useRef<HTMLDivElement>(null);
    const summaryId = useId();
    const isOffDate = (row: SlotImportRow) => !!occurrenceDays && !!row.startsAt && !occurrenceDays.has(localScheduleDayKey(row.startsAt));
    const isError = (row: { status: RowStatus, sheet: string, row: number }) => row.status === 'error' || serverErrors.has(rowKey(row));
    const count = (rows: { status: RowStatus }[], status: RowStatus) => rows.filter(row => row.status === status).length;
    const totalErrors = [...plan.people, ...plan.slots].filter(isError).length;
    const hasBoth = plan.people.length > 0 && plan.slots.length > 0;
    const activeTab: Tab = plan.slots.length === 0 ? 'people' : plan.people.length === 0 ? 'slots' : tab;
    const rows = activeTab === 'people' ? plan.people : plan.slots;
    const visibleRows = problemsOnly ? rows.filter(isError) : rows;
    const addedSlots = count(plan.slots, 'new');
    const repeatSlots = count(plan.slots, 'duplicate');
    const onLineup = count(plan.people, 'update') + count(plan.people, 'unchanged');
    const slotRowCount = plan.slots.length;
    const peopleRowCount = plan.people.length;
    const offDateSlots = plan.slots.filter(row => row.status !== 'error' && isOffDate(row)).length;

    useEffect(() => {
        summaryRef.current?.focus();
    }, [ready]);

    if (plan.people.length === 0 && plan.slots.length === 0) {
        return (
            <div className={classes.pick}>
                <div className={classes.failure} role="alert">
                    <IconAlertTriangle size={16}/>
                    {t`We couldn't find any rows to import. Add a header row with column names like Name, Start and End.`}
                </div>
                <Button variant="default" onClick={onReset}>{t`Choose another file`}</Button>
            </div>
        );
    }

    return (
        <div className={classes.preview}>
            <div className={classes.sourceBar}>
                <span className={classes.sourceName}><IconFileSpreadsheet size={16}/>{source.label}</span>
                <button type="button" className={classes.sourceReset} onClick={onReset}>{t`Choose another file`}</button>
            </div>

            {serverErrors.size > 0 && (
                <div className={classes.failure} role="alert" data-testid="lineup-import-rejected">
                    <IconAlertTriangle size={16}/>
                    {t`Some rows were rejected and nothing was imported. Fix the highlighted rows in your spreadsheet, then choose the file again.`}
                </div>
            )}

            {ready
                ? (
                    <div
                        ref={summaryRef}
                        className={classes.summary}
                        tabIndex={-1}
                        role="group"
                        aria-labelledby={['people', 'slots', 'stages', 'errors'].map(card => `${summaryId}-${card}`).join(' ')}
                        data-testid="lineup-import-summary"
                    >
                        <SummaryCard id={`${summaryId}-people`} icon={<IconUsers size={16}/>} value={peopleToAdd(plan)} label={t`people to add`} testId="lineup-import-people"
                                     detail={onLineup > 0 ? t`${onLineup} already on the lineup` : undefined}/>
                        <SummaryCard id={`${summaryId}-slots`} icon={<IconCalendarTime size={16}/>} value={addedSlots} label={t`time slots to add`} testId="lineup-import-slots"
                                     detail={repeatSlots > 0 ? t`${repeatSlots} already scheduled` : undefined}/>
                        <SummaryCard id={`${summaryId}-stages`} icon={<IconMapPin size={16}/>} value={plan.newStages.length} label={t`new stages or rooms`} testId="lineup-import-stages"
                                     detail={plan.newStages.slice(0, 3).join(', ') || undefined}/>
                        <SummaryCard id={`${summaryId}-errors`} icon={<IconAlertTriangle size={16}/>} value={totalErrors} label={t`rows to fix`} testId="lineup-import-errors"
                                     tone={totalErrors > 0 ? 'warning' : undefined}
                                     detail={totalErrors > 0 ? t`These are skipped` : t`Everything looks good`}/>
                    </div>
                )
                : (
                    <div ref={summaryRef} className={classes.summaryLoading} tabIndex={-1} role="status">
                        <Loader size="sm"/>{t`Checking your saved profiles…`}
                    </div>
                )}

            {plan.slots.length > 0 && (
                <div className={classes.notice}>
                    <IconClock size={14}/>
                    <span>{t`Times are read in the event's time zone (${timezone}).`}</span>
                    {plan.hasAmbiguousDates && (
                        <SegmentedControl
                            size="xs"
                            value={dayFirst ? 'day' : 'month'}
                            onChange={value => onDayFirstChange(value === 'day')}
                            aria-label={t`Date order`}
                            data-testid="lineup-import-date-order"
                            data={[
                                {value: 'day', label: t`Day/Month`},
                                {value: 'month', label: t`Month/Day`},
                            ]}
                        />
                    )}
                </div>
            )}

            {offDateSlots > 0 && (
                <p className={classes.notice} data-tone="warning">
                    <IconAlertTriangle size={14}/>
                    <span className={classes.noticeText}>
                        {plural(offDateSlots, {
                            one: "# time slot is on a day your event has no date, so it won't show on your event page.",
                            other: "# time slots are on days your event has no date, so they won't show on your event page.",
                        })}
                    </span>
                </p>
            )}

            {plan.unrecognisedSheets.length > 0 && (
                <p className={classes.notice}>
                    {t`Skipped sheets without recognisable columns:`} {plan.unrecognisedSheets.join(', ')}
                </p>
            )}

            <div className={classes.previewToolbar}>
                {hasBoth && (
                    <SegmentedControl
                        size="xs"
                        value={activeTab}
                        onChange={value => onTabChange(value as Tab)}
                        aria-label={t`Rows to show`}
                        data={[
                            {value: 'slots', label: t`Schedule (${slotRowCount})`},
                            {value: 'people', label: t`People (${peopleRowCount})`},
                        ]}
                    />
                )}
                {totalErrors > 0 && (
                    <Switch size="xs" label={t`Only rows to fix`} checked={problemsOnly} onChange={change => onProblemsOnlyChange(change.currentTarget.checked)}/>
                )}
            </div>

            <ul className={classes.rows} data-testid="lineup-import-rows">
                {visibleRows.map(row => activeTab === 'people'
                    ? <PersonRow key={rowKey(row)} row={row as PersonImportRow} serverError={serverErrors.get(rowKey(row))} ready={ready}/>
                    : <SlotRow key={rowKey(row)} row={row as SlotImportRow} serverError={serverErrors.get(rowKey(row))} offDate={isOffDate(row as SlotImportRow)}/>)}
            </ul>
        </div>
    );
};

const SummaryCard = ({id, icon, value, label, detail, tone, testId}: {
    id: string,
    icon: ReactNode,
    value: number,
    label: string,
    detail?: string,
    tone?: 'warning',
    testId: string,
}) => (
    <div id={id} className={classes.summaryCard} data-tone={tone} data-testid={testId}>
        <span className={classes.summaryLabel}>{icon}{label}</span>
        <strong>{value}</strong>
        {detail && <span className={classes.summaryDetail}>{detail}</span>}
    </div>
);

const StatusBadge = ({status, kind}: { status: RowStatus, kind: Tab }) => (
    <span className={classes.status} data-status={status}>{statusLabel(status, kind)}</span>
);

const PersonRow = ({row, serverError, ready}: { row: PersonImportRow, serverError?: string, ready: boolean }) => {
    const linkCount = row.links.length;
    const status: RowStatus = serverError ? 'error' : row.status;
    const details = [
        row.billingTier && billingTierLabel(row.billingTier),
        row.roleLabel,
        row.tagline,
        linkCount > 0 && plural(linkCount, {one: '# link', other: '# links'}),
        row.isHidden && t`Hidden`,
    ].filter(Boolean);
    const error = serverError ?? (row.error ? errorMessage(row.error) : null);

    return (
        <li className={classes.row} data-status={status}>
            <span className={classes.rowNumber}>{row.row}</span>
            <div className={classes.rowMain}>
                <span className={classes.rowTitle}>{row.name || '—'}</span>
                {error
                    ? <span className={classes.rowError}>{error}</span>
                    : details.length > 0 && <span className={classes.rowMeta}>{details.join(' · ')}</span>}
            </div>
            {(ready || status === 'error') && <StatusBadge status={status} kind="people"/>}
        </li>
    );
};

const SlotRow = ({row, serverError, offDate}: { row: SlotImportRow, serverError?: string, offDate: boolean }) => {
    const title = row.title || row.people.join('; ');
    const status: RowStatus = serverError ? 'error' : row.status;
    const time = row.startsAt
        ? `${formatDateWithLocale(row.startsAt, 'weekdayDate', 'UTC')} · ${formatDateWithLocale(row.startsAt, 'timeOnly', 'UTC')} – ${formatDateWithLocale(row.endsAt, 'timeOnly', 'UTC')}`
        : null;
    const newNames = row.newPeople.join('; ');
    const newCount = row.newPeople.length;
    const hasCommaName = row.people.some(name => name.includes(','));
    const error = serverError ?? (row.error ? errorMessage(row.error) : null);

    return (
        <li className={classes.row} data-status={status}>
            <span className={classes.rowNumber}>{row.row}</span>
            <div className={classes.rowMain}>
                <span className={classes.rowTitle}>{title || '—'}</span>
                {error
                    ? <span className={classes.rowError}>{error}</span>
                    : (
                        <span className={classes.rowMeta}>
                            {[time, row.stage, row.title && row.people.join('; ')].filter(Boolean).join(' · ')}
                        </span>
                    )}
                {!error && newCount > 0 && (
                    <span className={classes.rowNote}>
                        {plural(newCount, {one: `Creates a new profile for ${newNames}`, other: `Creates new profiles for ${newNames}`})}
                    </span>
                )}
                {!error && hasCommaName && (
                    <span className={classes.rowWarning}>{t`Treated as one person. Separate several people with a semicolon.`}</span>
                )}
                {!error && offDate && (
                    <span className={classes.rowWarning}>{t`No event date on this day, so it won't show on your event page`}</span>
                )}
            </div>
            <StatusBadge status={status} kind="slots"/>
        </li>
    );
};
