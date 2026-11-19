import {useEffect, useRef, useState} from "react";
import {Link} from "react-router";
import {t} from "@lingui/macro";
import {SegmentedControl, Select, Skeleton} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import {Pagination} from "../../../../../components/common/Pagination";
import {IdParam} from "../../../../../types.ts";
import {apiErrorStatus} from "../../../../../utilites/apiErrors.ts";
import {relativeDate} from "../../../../../utilites/dates.ts";
import {useGetCrmCampaignRecipients} from "../../../queries/useGetCrmCampaignRecipients.ts";
import {CrmCampaign, CrmCampaignRecipient, CrmRecipientFilter} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";
import {recipientFailureReason} from "../../../utilites/labels.ts";
import {StatusBadge, StatusTone} from "../../StatusBadge";
import classes from "./Campaign.module.scss";

const deliveryBadge = (recipient: CrmCampaignRecipient): { label: string, tone: StatusTone } => {
    if (recipient.status === 'FAILED') {
        return {label: t`Failed`, tone: 'negative'};
    }
    if (recipient.status === 'SKIPPED') {
        return {label: t`Skipped`, tone: 'neutral'};
    }
    if (recipient.status === 'PENDING' || recipient.status === 'SENDING') {
        return {label: t`Queued`, tone: 'neutral'};
    }
    if (recipient.complained_at) {
        return {label: t`Marked as spam`, tone: 'negative'};
    }
    if (recipient.bounced_at) {
        return {label: t`Bounced`, tone: 'negative'};
    }
    if (recipient.clicked_at) {
        return {label: t`Clicked`, tone: 'positive'};
    }
    if (recipient.opened_at) {
        return {label: t`Opened`, tone: 'positive'};
    }
    if (recipient.delivered_at) {
        return {label: t`Delivered`, tone: 'positive'};
    }
    return {label: t`Sent`, tone: 'positive'};
};

const statusNote = (recipient: CrmCampaignRecipient, isSms: boolean): string | null =>
    recipient.status === 'FAILED' || recipient.status === 'SKIPPED'
        ? recipientFailureReason(recipient.failure_reason, isSms)
        : null;

export const Recipients = ({organizerId, campaign}: { organizerId: IdParam, campaign: CrmCampaign }) => {
    const [filter, setFilter] = useState<CrmRecipientFilter>('all');
    const [page, setPage] = useState(1);
    const isSending = campaign.status === 'SENDING';
    const isSms = campaign.channel === 'SMS';
    const recipientsQuery = useGetCrmCampaignRecipients(organizerId, campaign.id, filter, page, isSending);
    const recipients = recipientsQuery.data?.data ?? [];
    const meta = recipientsQuery.data?.meta;
    const {stats} = campaign;
    const wasSending = useRef(isSending);
    const isNarrow = useMediaQuery('(max-width: 48em)');

    useEffect(() => {
        if (wasSending.current && !isSending) {
            void recipientsQuery.refetch();
        }
        wasSending.current = isSending;
    }, [isSending]);

    const emailFilters: { key: CrmRecipientFilter, label: string }[] = isSms || campaign.delivery_reports_available === false ? [] : [
        {key: 'delivered', label: t`Delivered · ${formatCount(stats.delivered)}`},
        {key: 'opened', label: t`Opened · ${formatCount(stats.opened)}`},
        {key: 'clicked', label: t`Clicked · ${formatCount(stats.clicked)}`},
        {key: 'bounced', label: t`Bounced or spam · ${formatCount(stats.bounced + stats.complained)}`},
    ];
    const filters: { key: CrmRecipientFilter, label: string }[] = [
        {key: 'all', label: t`Everyone · ${formatCount(campaign.recipients_count)}`},
        ...emailFilters,
        {key: 'not_sent', label: t`Not sent · ${formatCount(stats.failed + stats.skipped)}`},
    ];

    const filterOptions = filters.map(item => ({value: item.key, label: item.label}));
    const showFilter = (value: string) => {
        setFilter(value as CrmRecipientFilter);
        setPage(1);
    };

    return (
        <section className={classes.reportSection}>
            <div className={classes.reportHeader}>
                <h2>{t`Recipients`}</h2>
                <span>{t`Delivery status for each person`}</span>
            </div>
            {isNarrow ? (
                <Select
                    className={classes.recipientFilter}
                    aria-label={t`Filter recipients`}
                    data={filterOptions}
                    value={filter}
                    onChange={value => showFilter(value ?? 'all')}
                    allowDeselect={false}
                />
            ) : (
                <SegmentedControl
                    className={classes.recipientFilter}
                    size="xs"
                    aria-label={t`Filter recipients`}
                    data={filterOptions}
                    value={filter}
                    onChange={showFilter}
                />
            )}
            <div>
                {recipientsQuery.isLoading && <Skeleton height={160} mt="sm" radius="md"/>}

                {recipientsQuery.isError && (
                    <p className={classes.reportEmpty}>
                        {apiErrorStatus(recipientsQuery.error) === 403
                            ? t`This campaign was sent to people filtered by spend, so only members who can view financials can see who received it.`
                            : t`We couldn't load the recipients. Please refresh the page.`}
                    </p>
                )}

                {recipients.map(recipient => {
                    const badge = deliveryBadge(recipient);
                    const note = statusNote(recipient, isSms);
                    const address = isSms ? recipient.phone : recipient.email;
                    const content = (
                        <>
                            <span className={classes.recipientText}>
                                <span>{recipient.contact_id ? (recipient.full_name || address) : t`Deleted contact`}</span>
                                {recipient.contact_id && recipient.full_name && address && <small>{address}</small>}
                            </span>
                            <span className={classes.recipientDate}>
                                {recipient.sent_at ? relativeDate(recipient.sent_at) : ''}
                            </span>
                            <StatusBadge tone={badge.tone}>{badge.label}</StatusBadge>
                            {note && <span className={classes.recipientNote}>{note}</span>}
                        </>
                    );

                    return recipient.contact_id ? (
                        <Link
                            key={recipient.id}
                            to={`/manage/organizer/${organizerId}/audience/contacts?contact=${recipient.contact_id}`}
                            className={classes.recipient}
                            data-testid="campaign-recipient-row"
                        >
                            {content}
                        </Link>
                    ) : (
                        <div key={recipient.id} className={classes.recipient} data-testid="campaign-recipient-row">{content}</div>
                    );
                })}

                {recipientsQuery.data && recipients.length === 0 && (
                    <p className={classes.reportEmpty}>{t`Nobody here yet.`}</p>
                )}
            </div>
            {meta && meta.last_page > 1 && (
                <Pagination value={page} total={meta.last_page} onChange={setPage} size="sm"/>
            )}
        </section>
    );
};
