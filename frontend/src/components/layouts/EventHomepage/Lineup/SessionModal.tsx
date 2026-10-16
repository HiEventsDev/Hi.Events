import {t} from "@lingui/macro";
import {Modal} from "@mantine/core";
import {embeddedDialogStyles, useEmbeddedDialogPlacement} from "../../../widget/EmbeddedDialogOffset.tsx";
import {IconArrowLeft, IconCalendarPlus, IconChevronRight, IconClock, IconMapPin, IconX} from "@tabler/icons-react";
import dayjs from "dayjs";
import {Event} from "../../../../types.ts";
import {EventLineup, LineupEntry, ScheduleItem} from "../../../../api/lineup.client.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {htmlToText} from "../../../../utilites/helpers.ts";
import {scheduleItemTitle} from "../../../../utilites/lineup.ts";
import {CalendarEntry, calendarEntryForEvent} from "../../../../utilites/calendar.ts";
import {eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import {CalendarOptionsPopover} from "../../../common/CalendarOptionsPopover";
import {UserGeneratedContent} from "../../../common/UserGeneratedContent";
import {PerformerAvatar} from "./ScheduleTimeline.tsx";
import classes from "./Lineup.module.scss";

interface SessionModalProps {
    event: Event;
    lineup: EventLineup;
    item: ScheduleItem;
    entriesById: Map<number, LineupEntry>;
    portalTarget?: HTMLElement;
    onClose: () => void;
    onBack?: () => void;
    onOpenEntry: (entryId: number) => void;
}

const durationLabel = (item: ScheduleItem) => {
    const minutes = dayjs.utc(item.ends_at).diff(dayjs.utc(item.starts_at), 'minute');
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (hours === 0) {
        return t`${minutes} min`;
    }

    return remainder === 0 ? t`${hours} h` : t`${hours} h ${remainder} min`;
};

export const SessionModal = ({event, lineup, item, entriesById, portalTarget, onClose, onBack, onOpenEntry}: SessionModalProps) => {
    const embeddedPlacement = useEmbeddedDialogPlacement();
    const title = scheduleItemTitle(item, entriesById);
    const spaceIndex = lineup.spaces.findIndex(space => space.id === item.event_space_id);
    const space = spaceIndex >= 0 ? lineup.spaces[spaceIndex] : undefined;
    const performers = item.lineup_entry_ids
        .map(id => entriesById.get(id))
        .filter((entry): entry is LineupEntry => !!entry);
    const tz = event.timezone;

    const calendarEntry: CalendarEntry = {
        title: `${title} · ${event.title}`,
        startDate: dayjs.utc(item.starts_at).toISOString(),
        endDate: dayjs.utc(item.ends_at).toISOString(),
        description: [
            performers.map(entry => entry.profile.name).join(', '),
            htmlToText(item.description),
            eventHomepageUrl(event),
        ].filter(Boolean).join('\n\n'),
        location: [space?.name, space?.venue?.name || calendarEntryForEvent(event).location].filter(Boolean).join(', '),
        filename: title,
    };

    return (
        <Modal
            opened
            onClose={onClose}
            withCloseButton={false}
            centered={!embeddedPlacement}
            yOffset={embeddedPlacement?.yOffset}
            styles={embeddedDialogStyles(embeddedPlacement)}
            size={560}
            padding={0}
            radius={24}
            portalProps={{target: portalTarget}}
            overlayProps={{backgroundOpacity: 0.6, blur: 6}}
            title={title}
            classNames={{content: classes.profileModal, body: classes.profileModalBody, header: classes.visuallyHidden}}
        >
            <div className={classes.dialogControls}>
                {onBack && (
                    <button type="button" className={classes.dialogBack} onClick={onBack} aria-label={t`Back`}>
                        <IconArrowLeft size={18}/>
                    </button>
                )}
                <button type="button" className={classes.sessionClose} onClick={onClose} aria-label={t`Close`}>
                    <IconX size={18}/>
                </button>
            </div>
            <div className={classes.sessionHeader} data-space-index={space ? spaceIndex % 6 : undefined} data-has-back={onBack ? true : undefined} data-testid="session-modal">
                {space && (
                    <span className={classes.sessionSpace}>
                        <span className={classes.spaceDot} data-space-index={spaceIndex % 6}/>
                        {space.name}
                    </span>
                )}
                <h3 className={classes.sessionTitle}>{title}</h3>
                <div className={classes.sessionFacts}>
                    <span>
                        <IconClock size={16}/>
                        {formatDateWithLocale(item.starts_at, 'weekdayDate', tz)}
                        {' · '}
                        {formatDateWithLocale(item.starts_at, 'timeOnly', tz)} – {formatDateWithLocale(item.ends_at, 'timeOnly', tz)}
                        <span className={classes.sessionDuration}>{durationLabel(item)}</span>
                    </span>
                    {space?.venue?.name && (
                        <span><IconMapPin size={16}/>{space.venue.name}</span>
                    )}
                </div>
                <CalendarOptionsPopover entry={calendarEntry}>
                    <button type="button" className={classes.sessionCalendarButton} data-testid="session-add-to-calendar">
                        <IconCalendarPlus size={18}/>
                        {t`Add to calendar`}
                    </button>
                </CalendarOptionsPopover>
            </div>

            {(item.description || performers.length > 0) && (
                <div className={classes.profileContent}>
                    {item.description && <UserGeneratedContent className={classes.profileBio} html={item.description}/>}

                    {performers.length > 0 && (
                        <ul className={classes.sessionPeople}>
                            {performers.map(entry => (
                                <li key={entry.id}>
                                    <button type="button" className={classes.sessionPerson} onClick={() => onOpenEntry(entry.id)}>
                                        <PerformerAvatar entry={entry} size="lg"/>
                                        <span className={classes.sessionPersonText}>
                                            <span className={classes.sessionPersonName}>{entry.profile.name}</span>
                                            {(entry.role_label || entry.profile.tagline) && (
                                                <span className={classes.sessionPersonTagline}>{entry.role_label || entry.profile.tagline}</span>
                                            )}
                                        </span>
                                        <IconChevronRight size={18} className={classes.sessionPersonChevron}/>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </Modal>
    );
};
