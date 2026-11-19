import {Link} from "react-router";
import {plural, t} from "@lingui/macro";
import {Avatar, Button, Skeleton} from "@mantine/core";
import {IconChevronRight} from "@tabler/icons-react";
import {IdParam} from "../../../../types.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useCrmAccess} from "../../hooks/useCrmAccess.ts";
import {isAudienceBuilt} from "../../hooks/useCrmReady.ts";
import {useGetCrmSyncStatus} from "../../queries/useGetCrmSyncStatus.ts";
import {useGetCrmEventAudience} from "../../queries/useGetCrmEventAudience.ts";
import {CrmEventAudience} from "../../types.ts";
import {contactAvatarColor, contactInitials, formatCount, formatPercent} from "../../utilites/format.ts";
import shared from "../Audience.module.scss";
import classes from "./EventDashboardAudience.module.scss";

const TurnoutStat = ({audience}: { audience: CrmEventAudience }) => {
    const usualRate = audience.baseline_show_rate === null ? '' : formatPercent(audience.baseline_show_rate);
    const ticketsLabel = formatCount(audience.tickets_count);

    if (audience.checked_in_count > 0) {
        return (
            <div className={shared.stat}>
                <span className={shared.statLabel}>{t`Checked in`}</span>
                <span className={shared.statValue}>{formatCount(audience.checked_in_count)}</span>
                <span className={shared.statHint}>
                    {t`${formatPercent(Math.round(audience.checked_in_count / Math.max(audience.tickets_count, 1) * 100))} of tickets`}
                </span>
            </div>
        );
    }

    return (
        <div className={shared.stat}>
            <span className={shared.statLabel}>{t`Likely to turn up`}</span>
            <span className={shared.statValue}>
                {audience.expected_tickets === null ? '–' : formatCount(audience.expected_tickets)}
            </span>
            <span className={shared.statHint}>
                {audience.baseline_show_rate === null
                    ? t`Needs a past event with check-ins`
                    : t`Tickets: ${ticketsLabel}. Usual turnout: ${usualRate}.`}
            </span>
        </div>
    );
};

const AudienceInsights = ({audience, basePath}: { audience: CrmEventAudience, basePath: string }) => {
    const returningShare = audience.returning_count / audience.contacts_count * 100;
    const maxShared = Math.max(...audience.overlaps.map(overlap => overlap.shared_contacts), 1);
    const returningCount = formatCount(audience.returning_count);
    const contactsLabel = formatCount(audience.contacts_count);

    return (
        <>
            <div className={shared.stats}>
                <div className={shared.stat}>
                    <span className={shared.statLabel}>{t`Booked with you before`}</span>
                    <span className={shared.statValue}>{formatPercent(Math.round(returningShare))}</span>
                    <span className={shared.statHint}>
                        {t`Ticket holders: ${returningCount} of ${contactsLabel}`}
                    </span>
                </div>
                <div className={shared.stat}>
                    <span className={shared.statLabel}>{t`New to you`}</span>
                    <span className={shared.statValue}>{formatCount(audience.new_count)}</span>
                    <span className={shared.statHint}>{t`First event with you`}</span>
                </div>
                <TurnoutStat audience={audience}/>
                <div className={shared.stat}>
                    <span className={shared.statLabel}>{t`Subscribed`}</span>
                    <span className={shared.statValue}>{formatCount(audience.mailable_count)}</span>
                    <span className={shared.statHint}>{t`Ticket holders you can email`}</span>
                </div>
            </div>

            {audience.likely_no_show_contacts > 0 && (
                <p className={classes.note}>
                    {plural(audience.likely_no_show_contacts, {
                        one: '# ticket holder has missed every past event they booked.',
                        other: '# ticket holders have missed every past event they booked.',
                    })}
                </p>
            )}

            <div className={shared.grid}>
                <section className={shared.panel}>
                    <div className={shared.panelHeader}>
                        <h2 className={shared.panelTitle}>{t`Most events booked`}</h2>
                        <span className={shared.panelHint}>{t`Ticket holders who have booked the most of your events`}</span>
                    </div>
                    <div className={`${shared.rows} ${shared.panelFlush}`}>
                        {audience.top_contacts.length === 0 && (
                            <div className={shared.empty}><p>{t`Everyone here is new to you.`}</p></div>
                        )}
                        {audience.top_contacts.map(contact => (
                            <Link key={contact.id} to={`${basePath}/contacts?contact=${contact.id}`} className={shared.personRow}>
                                <Avatar size={34} radius="xl" color={contactAvatarColor(contact)}>{contactInitials(contact)}</Avatar>
                                <span className={shared.personText}>
                                    <span className={shared.personName}>{contact.full_name || contact.email}</span>
                                    <span className={shared.personMeta}>{contact.email}</span>
                                </span>
                                <span className={shared.personFigure}>
                                    {plural(contact.events_count, {one: '# event', other: '# events'})}
                                    <small>{plural(contact.attended_count, {one: '# check-in', other: '# check-ins'})}</small>
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>
                <section className={shared.panel}>
                    <div className={shared.panelHeader}>
                        <h2 className={shared.panelTitle}>{t`Also went to`}</h2>
                        <span className={shared.panelHint}>{t`Your other events these people booked`}</span>
                    </div>
                    <div className={shared.panelBody}>
                        {audience.overlaps.length === 0 ? (
                            <div className={shared.empty}><p>{t`No overlap with your other events yet.`}</p></div>
                        ) : (
                            <div className={shared.bars}>
                                {audience.overlaps.map(overlap => (
                                    <div key={overlap.event_id} className={shared.bar}>
                                        <span className={shared.barLabel} title={overlap.title}>{overlap.title}</span>
                                        <span className={shared.barTrack}>
                                            <span className={shared.barFill} style={{width: `${overlap.shared_contacts / maxShared * 100}%`}}/>
                                        </span>
                                        <span className={shared.barValue}>{formatCount(overlap.shared_contacts)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
};

const AudienceSection = ({organizerId, eventId}: { organizerId: IdParam, eventId: IdParam }) => {
    const syncQuery = useGetCrmSyncStatus(organizerId);
    const isReady = isAudienceBuilt(syncQuery.data);
    const audienceQuery = useGetCrmEventAudience(organizerId, eventId, isReady);
    const basePath = `/manage/organizer/${organizerId}/audience`;

    if (!syncQuery.isSuccess || audienceQuery.isError || audienceQuery.data?.contacts_count === 0) {
        return null;
    }

    return (
        <section className={`${shared.page} ${classes.section}`} data-testid="event-dashboard-audience">
            <div className={classes.header}>
                <span className={classes.label}>{t`Audience`}</span>
                {isReady && (
                    <Link to={basePath} className={`${shared.panelLink} ${classes.fullLink}`}>
                        {t`See full audience`}
                        <IconChevronRight size={14}/>
                    </Link>
                )}
            </div>

            {!isReady && (
                <div className={`${shared.panel} ${classes.setup}`}>
                    <p>{t`Set up Audience to see who has been to your events before, who is likely to turn up and where else they go.`}</p>
                    <Button variant="default" size="xs" component={Link} to={basePath}>{t`Open Audience`}</Button>
                </div>
            )}

            {audienceQuery.isLoading && <Skeleton height={92} radius="lg"/>}

            {audienceQuery.data && <AudienceInsights audience={audienceQuery.data} basePath={basePath}/>}
        </section>
    );
};

export const EventDashboardAudience = ({organizerId, eventId}: { organizerId: IdParam | undefined, eventId: IdParam | undefined }) => {
    const {isVisible} = useCrmAccess();
    const {canManageOrganizer} = useAccess();

    if (!isVisible || !organizerId || !eventId || !canManageOrganizer(organizerId)) {
        return null;
    }

    return <AudienceSection organizerId={organizerId} eventId={eventId}/>;
};
