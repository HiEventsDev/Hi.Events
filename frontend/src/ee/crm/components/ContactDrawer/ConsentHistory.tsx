import {ReactNode} from "react";
import {Link} from "react-router";
import {t} from "@lingui/macro";
import {Button, Tooltip} from "@mantine/core";
import {IconMailCheck, IconMailOff, IconMailX, IconMessageCheck, IconMessageOff, IconRefresh} from "@tabler/icons-react";
import {SideDrawerSection} from "../../../../components/common/SideDrawer";
import {IdParam} from "../../../../types.ts";
import {formatDateWithLocale, relativeDate} from "../../../../utilites/dates.ts";
import {useGetCrmContactConsent} from "../../queries/useGetCrmContactConsent.ts";
import {CrmConsentAction, CrmConsentEvent} from "../../types.ts";
import {consentActionLabel, smsConsentActionLabel} from "../../utilites/labels.ts";
import {formatPhoneNumber} from "../../../../utilites/phoneNumber.ts";
import classes from "./ContactDrawer.module.scss";

const ACTION_ICONS: Record<CrmConsentAction, { icon: typeof IconMailCheck, tone?: 'good' | 'bad' }> = {
    OPT_IN: {icon: IconMailCheck, tone: 'good'},
    OPT_OUT: {icon: IconMailOff},
    BOUNCED: {icon: IconMailX, tone: 'bad'},
    COMPLAINED: {icon: IconMailX, tone: 'bad'},
    BOUNCE_CLEARED: {icon: IconRefresh},
};

const SMS_ACTION_ICONS: Partial<Record<CrmConsentAction, { icon: typeof IconMailCheck, tone?: 'good' | 'bad' }>> = {
    OPT_IN: {icon: IconMessageCheck, tone: 'good'},
    OPT_OUT: {icon: IconMessageOff},
};

const smsConsentSource = (event: CrmConsentEvent): string => {
    const user = event.user_name;

    switch (event.source) {
        case 'ORDER':
            return t`Ticked the text message box at checkout`;
        case 'SMS_REPLY':
            return event.action === 'OPT_IN' ? t`Replied START to a text message` : t`Replied STOP to a text message`;
        case 'UNSUBSCRIBE_LINK':
            return t`Opt-out link in a text message`;
        case 'CAMPAIGN':
            return t`Twilio reported that this number had opted out`;
        default:
            return user ? t`By ${user}` : t`By a team member`;
    }
};

const consentSource = (event: CrmConsentEvent): string => {
    if (event.channel === 'SMS') {
        return smsConsentSource(event);
    }

    const campaign = event.campaign_name;
    const file = event.import_file_name;
    const user = event.user_name;

    switch (event.source) {
        case 'ORDER':
            return t`Ticked the marketing box at checkout`;
        case 'IMPORT':
            if (event.action !== 'OPT_IN') {
                return file ? t`Imported from ${file}` : t`Imported`;
            }
            return file ? t`Imported from ${file}, with consent confirmed` : t`Imported, with consent confirmed`;
        case 'MANUAL':
            if (event.action === 'BOUNCE_CLEARED') {
                return user ? t`Reactivated by ${user}` : t`Reactivated by a team member`;
            }
            return user ? t`By ${user}` : t`By a team member`;
        case 'UNSUBSCRIBE_LINK':
            return campaign ? t`Unsubscribe link in "${campaign}"` : t`Unsubscribe link in a campaign`;
        case 'CAMPAIGN':
            return campaign ? t`Reported on "${campaign}"` : t`Reported on a campaign`;
        case 'CONTACT':
            return t`Resubscribed on the unsubscribe page`;
        case 'SMS_REPLY':
            return smsConsentSource(event);
        case 'FORM': {
            const form = event.form_name;
            return form ? t`Confirmed by email after signing up with "${form}"` : t`Confirmed by email after signing up with a form`;
        }
    }
};

interface ConsentGroup {
    key: string;
    latest: CrmConsentEvent;
    count: number;
}

const groupKey = (event: CrmConsentEvent): string =>
    [event.channel, event.action, event.channel === 'SMS' ? event.phone : event.email, consentSource(event)].join('|');

const groupRepeats = (events: CrmConsentEvent[]): ConsentGroup[] => events.reduce<ConsentGroup[]>((groups, event) => {
    const previous = groups[groups.length - 1];
    const key = groupKey(event);

    if (previous?.key === key) {
        previous.count += 1;
    } else {
        groups.push({key, latest: event, count: 1});
    }

    return groups;
}, []);

const ConsentRow = ({group, timezone, showsEmailAddress}: { group: ConsentGroup, timezone: string, showsEmailAddress: boolean }) => {
    const {latest: event, count} = group;
    const {icon: Icon, tone} = (event.channel === 'SMS' ? SMS_ACTION_ICONS[event.action] : undefined) ?? ACTION_ICONS[event.action];
    const address = event.channel === 'SMS' ? formatPhoneNumber(event.phone) : showsEmailAddress ? event.email : null;
    const label = event.channel === 'SMS' ? smsConsentActionLabel(event.action) : consentActionLabel(event.action);
    const when = relativeDate(event.occurred_at);

    const content: ReactNode = (
        <>
            <span className={classes.eventIcon} data-tone={tone}><Icon size={15} stroke={1.8}/></span>
            <span className={classes.eventTitle}>{count > 1 ? t`${label} ×${count}` : label}</span>
            <span className={classes.eventAmount}/>
            <span className={classes.eventMeta}>
                {[consentSource(event), address].filter(Boolean).join(' · ')}<br/>
                <Tooltip label={formatDateWithLocale(event.occurred_at, 'fullDateTime', timezone)} openDelay={300}>
                    <span>{count > 1 ? t`Most recently ${when}` : when}</span>
                </Tooltip>
            </span>
        </>
    );

    if (count === 1 && event.order_id && event.event_id) {
        return (
            <Link
                to={`/manage/event/${event.event_id}/orders#order-${event.order_id}`}
                className={classes.event}
                data-link
                title={t`View order`}
            >
                {content}
            </Link>
        );
    }

    return <div className={classes.event}>{content}</div>;
};

export const ConsentHistory = ({organizerId, contactId, timezone, showsEmailAddress}: {
    organizerId: IdParam,
    contactId: IdParam,
    timezone: string,
    showsEmailAddress: boolean,
}) => {
    const consent = useGetCrmContactConsent(organizerId, contactId);
    const events = consent.data?.pages.flatMap(page => page.data) ?? [];

    return (
        <SideDrawerSection title={t`Consent history`} count={consent.data?.pages[0]?.meta.total}>
            {consent.isLoading && <p className={classes.empty}>{t`Loading…`}</p>}
            {consent.isError && (
                <p className={classes.empty}>{t`We couldn't load this contact's consent history. Please refresh the page.`}</p>
            )}
            {consent.data && events.length === 0 && (
                <p className={classes.empty}>{t`No opt-ins or opt-outs recorded yet.`}</p>
            )}
            <div className={classes.timeline}>
                {groupRepeats(events).map(group => (
                    <ConsentRow
                        key={`${group.latest.channel}-${group.latest.id}`}
                        group={group}
                        timezone={timezone}
                        showsEmailAddress={showsEmailAddress}
                    />
                ))}
            </div>
            {consent.hasNextPage && (
                <Button
                    className={classes.loadMore}
                    variant="default"
                    size="xs"
                    fullWidth
                    loading={consent.isFetchingNextPage}
                    onClick={() => consent.fetchNextPage()}
                >
                    {t`Show earlier consent changes`}
                </Button>
            )}
        </SideDrawerSection>
    );
};
