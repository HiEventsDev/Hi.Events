import {ReactNode} from "react";
import {Link} from "react-router";
import {t} from "@lingui/macro";
import {Button, Tooltip} from "@mantine/core";
import {
    IconCheck,
    IconClockPause,
    IconCreditCardRefund,
    IconFileImport,
    IconMail,
    IconMessage,
    IconReceipt,
    IconShoppingCartX,
    IconTicket,
    IconX,
} from "@tabler/icons-react";
import {SideDrawerSection} from "../../../../components/common/SideDrawer";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {formatDateWithLocale, relativeDate} from "../../../../utilites/dates.ts";
import type {useGetCrmContactActivities} from "../../queries/useGetCrmContactActivities.ts";
import {CrmActivityType, CrmContactActivity} from "../../types.ts";
import {activityLabel} from "../../utilites/labels.ts";
import {personName} from "./names.ts";
import classes from "./ContactDrawer.module.scss";

const ACTIVITY_ICONS: Record<CrmActivityType, { icon: typeof IconTicket, tone?: 'good' | 'bad' | 'accent' }> = {
    ORDER_COMPLETED: {icon: IconReceipt, tone: 'accent'},
    ORDER_AWAITING_PAYMENT: {icon: IconClockPause},
    ORDER_CANCELLED: {icon: IconX, tone: 'bad'},
    ORDER_REFUNDED: {icon: IconCreditCardRefund, tone: 'bad'},
    CHECKOUT_ABANDONED: {icon: IconShoppingCartX},
    TICKET_HELD: {icon: IconTicket},
    CHECKED_IN: {icon: IconCheck, tone: 'good'},
    WAITLIST_JOINED: {icon: IconClockPause},
    CAMPAIGN_SENT: {icon: IconMail, tone: 'accent'},
    IMPORTED: {icon: IconFileImport},
};

const AMOUNT_TYPES: CrmActivityType[] = ['ORDER_COMPLETED', 'ORDER_REFUNDED', 'ORDER_AWAITING_PAYMENT'];

const activityTitle = (activity: CrmContactActivity): string => {
    const name = personName(activity.first_name, activity.last_name);

    if (activity.role === 'GUEST' && name) {
        return activity.type === 'CHECKED_IN'
            ? t`${name} checked in (guest)`
            : t`Ticket for ${name} (guest)`;
    }

    if (activity.type === 'TICKET_HELD' && activity.product_title) {
        return activity.product_title;
    }

    if (activity.type === 'CAMPAIGN_SENT' && activity.label) {
        return activity.campaign_channel === 'SMS'
            ? t`Sent the text "${activity.label}"`
            : t`Sent the email "${activity.label}"`;
    }

    return activityLabel(activity.type);
};

const ActivityRow = ({activity, timezone}: { activity: CrmContactActivity, timezone: string }) => {
    const {icon: typeIcon, tone} = ACTIVITY_ICONS[activity.type];
    const Icon = activity.campaign_channel === 'SMS' ? IconMessage : typeIcon;
    const zone = activity.event_timezone ?? timezone;
    const meta = [
        activity.event_title,
        activity.occurrence_start_date && formatDateWithLocale(activity.occurrence_start_date, 'shortDate', zone),
        activity.type === 'ORDER_COMPLETED' && activity.label ? t`Promo code ${activity.label}` : null,
    ].filter(Boolean).join(' · ');

    const content: ReactNode = (
        <>
            <span className={classes.eventIcon} data-tone={tone}><Icon size={15} stroke={1.8}/></span>
            <span className={classes.eventTitle}>{activityTitle(activity)}</span>
            <span className={classes.eventAmount}>
                {activity.amount != null && activity.currency && AMOUNT_TYPES.includes(activity.type)
                    ? `${activity.type === 'ORDER_REFUNDED' ? '−' : ''}${formatCurrency(activity.amount, activity.currency)}`
                    : null}
            </span>
            <span className={classes.eventMeta}>
                {meta && <>{meta}<br/></>}
                <Tooltip label={formatDateWithLocale(activity.occurred_at, 'fullDateTime', zone)} openDelay={300}>
                    <span>{relativeDate(activity.occurred_at)}</span>
                </Tooltip>
            </span>
        </>
    );

    if (activity.order_id && activity.event_id) {
        return (
            <Link
                to={`/manage/event/${activity.event_id}/orders#order-${activity.order_id}`}
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

export const ActivityTimeline = ({activities, timezone}: {
    activities: ReturnType<typeof useGetCrmContactActivities>,
    timezone: string,
}) => {
    const timeline = activities.data?.pages.flatMap(page => page.data) ?? [];

    return (
        <SideDrawerSection title={t`Activity`} count={activities.data?.pages[0]?.meta.total}>
            {activities.isLoading && <p className={classes.empty}>{t`Loading…`}</p>}
            {activities.isError && (
                <p className={classes.empty}>{t`We couldn't load this contact's activity. Please refresh the page.`}</p>
            )}
            {activities.data && timeline.length === 0 && (
                <p className={classes.empty}>{t`Nothing yet. Orders, tickets and check-ins will show up here.`}</p>
            )}
            <div className={classes.timeline}>
                {timeline.map(activity => (
                    <ActivityRow key={activity.id} activity={activity} timezone={timezone}/>
                ))}
            </div>
            {activities.hasNextPage && (
                <Button
                    className={classes.loadMore}
                    variant="default"
                    size="xs"
                    fullWidth
                    loading={activities.isFetchingNextPage}
                    onClick={() => activities.fetchNextPage()}
                >
                    {t`Show earlier activity`}
                </Button>
            )}
        </SideDrawerSection>
    );
};
