import {useEffect, useMemo, useRef, useState} from "react";
import {Event, EventOccurrence} from "../../../../types.ts";
import {EventLineup, LineupEntry} from "../../../../api/lineup.client.ts";
import {useGetEventLineupPublic} from "../../../../queries/useGetEventLineupPublic.ts";
import {getLineupTerminology} from "../../../../utilites/lineup.ts";
import {lineupForOccurrence} from "../../../../utilites/lineupSchedule.ts";
import {LineupGrid} from "./LineupGrid.tsx";
import {ScheduleTimeline} from "./ScheduleTimeline.tsx";
import {ProfileModal} from "./ProfileModal.tsx";
import {SessionModal} from "./SessionModal.tsx";
import {useReturnFocusOnClose} from "../../../../hooks/useReturnFocusOnClose.ts";
import classes from "./Lineup.module.scss";

export interface HomepageSectionClasses {
    section: string;
    sectionHeader: string;
    sectionTitle: string;
}

interface LineupSectionProps {
    event: Event;
    occurrence?: EventOccurrence;
    heading?: string | null;
    sectionClasses: HomepageSectionClasses;
    compact?: boolean;
}

const usePublicLineup = (event: Event, occurrence?: EventOccurrence) => {
    const {data: fullLineup} = useGetEventLineupPublic(event.id);

    const lineup = useMemo(
        () => fullLineup && occurrence ? lineupForOccurrence(fullLineup, occurrence, event.timezone) : fullLineup,
        [fullLineup, occurrence, event.timezone],
    );

    const entriesById = useMemo(
        () => new Map<number, LineupEntry>((lineup?.entries ?? []).map(entry => [entry.id, entry])),
        [lineup?.entries],
    );

    return {lineup, entriesById};
};

const SESSION_HASH_PREFIX = '#session-';

interface DialogTarget {
    kind: 'entry' | 'item';
    id: number;
}

type OpenDialog = (DialogTarget & { back?: DialogTarget }) | null;

const useLineupDialog = (lineup: EventLineup | undefined, deepLink: boolean) => {
    const [dialog, setDialog] = useState<OpenDialog>(null);
    useReturnFocusOnClose(dialog !== null);

    useEffect(() => {
        if (!deepLink || !lineup || !window.location.hash.startsWith(SESSION_HASH_PREFIX)) {
            return;
        }
        const itemId = Number(window.location.hash.slice(SESSION_HASH_PREFIX.length));
        if (lineup.schedule_items.some(item => item.id === itemId)) {
            setDialog({kind: 'item', id: itemId});
        }
    }, [deepLink, lineup]);

    const show = (target: DialogTarget, back?: DialogTarget) => {
        setSessionHash(target.kind === 'item' ? target.id : null);
        setDialog({...target, back});
    };

    const previous = (kind: DialogTarget['kind']): DialogTarget | undefined => dialog && dialog.kind !== kind
        ? {kind: dialog.kind, id: dialog.id}
        : undefined;

    const setSessionHash = (itemId: number | null) => {
        if (!deepLink) {
            return;
        }
        const {pathname, search} = window.location;
        window.history.replaceState(window.history.state, '', itemId ? `${pathname}${search}${SESSION_HASH_PREFIX}${itemId}` : `${pathname}${search}`);
    };

    return {
        dialog,
        openEntry: (id: number) => show({kind: 'entry', id}, previous('entry')),
        openItem: (id: number) => show({kind: 'item', id}, previous('item')),
        back: dialog?.back ? () => show(dialog.back!) : undefined,
        close: () => {
            setSessionHash(null);
            setDialog(null);
        },
    };
};

const LineupDialogHost = ({event, lineup, entriesById, anchor, dialogState}: {
    event: Event,
    lineup: EventLineup,
    entriesById: Map<number, LineupEntry>,
    anchor: HTMLElement | null,
    dialogState: ReturnType<typeof useLineupDialog>,
}) => {
    const {dialog, openEntry, openItem, back, close} = dialogState;
    const portalTarget = anchor?.closest('main') ?? undefined;
    const entry = dialog?.kind === 'entry' ? entriesById.get(dialog.id) : undefined;
    const item = dialog?.kind === 'item' ? lineup.schedule_items.find(candidate => candidate.id === dialog.id) : undefined;

    if (entry) {
        return <ProfileModal event={event} entry={entry} lineup={lineup} portalTarget={portalTarget} onClose={close} onBack={back} onOpenItem={openItem}/>;
    }

    if (item) {
        return (
            <SessionModal
                event={event}
                lineup={lineup}
                item={item}
                entriesById={entriesById}
                portalTarget={portalTarget}
                onClose={close}
                onBack={back}
                onOpenEntry={openEntry}
            />
        );
    }

    return null;
};

export const EventLineupSection = ({event, occurrence, heading, sectionClasses, compact}: LineupSectionProps) => {
    const {lineup, entriesById} = usePublicLineup(event, occurrence);
    const dialogState = useLineupDialog(lineup, false);
    const anchorRef = useRef<HTMLElement>(null);

    if (!lineup || lineup.entries.length === 0) {
        return null;
    }

    return (
        <section ref={anchorRef} className={`${sectionClasses.section} ${classes.queryContainer}`} id="lineup" data-testid="event-lineup-section">
            <div className={sectionClasses.sectionHeader}>
                <h2 className={sectionClasses.sectionTitle}>{heading || getLineupTerminology(event.category).lineup}</h2>
            </div>
            <LineupGrid entries={lineup.entries} onOpen={dialogState.openEntry} compact={compact}/>
            <LineupDialogHost event={event} lineup={lineup} entriesById={entriesById} anchor={anchorRef.current} dialogState={dialogState}/>
        </section>
    );
};

export const EventScheduleSection = ({event, occurrence, heading, sectionClasses}: LineupSectionProps) => {
    const {lineup, entriesById} = usePublicLineup(event, occurrence);
    const dialogState = useLineupDialog(lineup, true);
    const anchorRef = useRef<HTMLElement>(null);

    if (!lineup || lineup.schedule_items.length === 0) {
        return null;
    }

    return (
        <section ref={anchorRef} className={`${sectionClasses.section} ${classes.queryContainer}`} id="schedule" data-testid="event-schedule-section">
            <ScheduleTimeline
                event={event}
                lineup={lineup}
                entriesById={entriesById}
                title={heading || getLineupTerminology(event.category).schedule}
                sectionClasses={sectionClasses}
                onOpenEntry={dialogState.openEntry}
                onOpenItem={dialogState.openItem}
            />
            <LineupDialogHost event={event} lineup={lineup} entriesById={entriesById} anchor={anchorRef.current} dialogState={dialogState}/>
        </section>
    );
};
