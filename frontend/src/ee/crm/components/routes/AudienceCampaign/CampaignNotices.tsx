import {Link} from "react-router";
import {plural, t} from "@lingui/macro";
import {IconClock} from "@tabler/icons-react";
import {IdParam} from "../../../../../types.ts";
import {apiErrorStatus} from "../../../../../utilites/apiErrors.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {useGetCrmCampaigns} from "../../../queries/useGetCrmCampaigns.ts";
import {CrmCampaign} from "../../../types.ts";
import {campaignStatusReason} from "../../../utilites/labels.ts";
import {smsCountryLabel} from "../../../utilites/smsCountries.ts";
import {Notice} from "../../Notice";
import {SendGateReason} from "../../SendGateReason";
import classes from "./Campaign.module.scss";

const AUTOMATION_RUN_REASONS = ['REJECTED_BY_REVIEW', 'REVIEW_EXPIRED'];

interface CampaignNoticesProps {
    organizerId: IdParam;
    campaign: CrmCampaign | undefined;
    isSms: boolean;
    timezone: string;
    loadError: unknown;
    isPausedByLicence: boolean;
    isRestricted: boolean;
    isEditable: boolean;
}

export const CampaignNotices = ({organizerId, campaign, isSms, timezone, loadError, isPausedByLicence, isRestricted, isEditable}: CampaignNoticesProps) => {
    const reason = campaign ? campaignStatusReason(campaign.status_reason, !!campaign.automation_id, campaign.status_reason_params) : null;
    const isScheduled = campaign?.status === 'SCHEDULED' && !isPausedByLicence;
    const isCancelled = campaign?.status === 'CANCELLED' && !reason;
    const listMetaQuery = useGetCrmCampaigns(organizerId, {}, 1, isEditable || isPausedByLicence);
    const sendGate = isEditable ? listMetaQuery.data?.meta.send_gate : null;
    const sendWindowHours = listMetaQuery.data?.meta.scheduled_send_window_hours;
    const waiting = campaign?.sms_waiting ?? [];

    return (
        <>
            {!!loadError && (
                <Notice>
                    {apiErrorStatus(loadError) === 404
                        ? t`This campaign doesn't exist any more.`
                        : t`We couldn't load this campaign. Please refresh the page.`}
                </Notice>
            )}

            {sendGate?.blocked_reason && (
                <Notice
                    title={isSms ? t`You can write this text message, but you can't send it yet.` : t`You can write this campaign, but you can't send it yet.`}
                    data-testid="campaign-send-gate-notice"
                >
                    <SendGateReason
                        organizerId={organizerId}
                        reason={sendGate.blocked_reason}
                        code={sendGate.blocked_reason_code}
                        params={sendGate.blocked_reason_params}
                    />
                </Notice>
            )}

            {isPausedByLicence && (
                <Notice>
                    {t`Scheduled campaigns don't send while your licence is inactive.`}
                    {sendWindowHours !== undefined && (
                        <>
                            {' '}
                            {plural(sendWindowHours, {
                                one: 'If this one misses its time by more than # hour, it goes back to your drafts and we email you.',
                                other: 'If this one misses its time by more than # hours, it goes back to your drafts and we email you.',
                            })}
                        </>
                    )}
                </Notice>
            )}

            {waiting.length > 0 && (
                <Notice tone="info" icon={<IconClock size={18} stroke={1.8}/>} data-testid="campaign-sms-waiting">
                    {t`Some texts are waiting for sending hours. In countries with several time zones, texts wait until it is between 9am and 8pm in all of them. They go out on their own, so you don't need to do anything.`}
                    <ul className={classes.waiting}>
                        {waiting.map(item => {
                            const country = smsCountryLabel(item.country);
                            const resumesAt = formatDateWithLocale(item.next_sending_at, 'shortDateTime', timezone);

                            return (
                                <li key={item.country}>
                                    {plural(item.pending_count, {
                                        one: `${country}: # text waiting. Sending starts again ${resumesAt} (${timezone}).`,
                                        other: `${country}: # texts waiting. Sending starts again ${resumesAt} (${timezone}).`,
                                    })}
                                </li>
                            );
                        })}
                    </ul>
                </Notice>
            )}

            {campaign && reason && (
                <Notice tone={campaign.status === 'PENDING_REVIEW' ? 'info' : 'warning'}>
                    {reason}
                    {campaign.automation_id && AUTOMATION_RUN_REASONS.includes(campaign.status_reason ?? '') && (
                        <>
                            {' '}
                            <Link to={`/manage/organizer/${organizerId}/audience/automations`}>{t`Go to automations`}</Link>
                        </>
                    )}
                    {campaign.review_note && campaign.status_reason === 'REJECTED_BY_REVIEW' && (
                        <span className={classes.reviewNote} data-testid="campaign-review-note">
                            <b>{t`Note from our team:`}</b> {campaign.review_note}
                        </span>
                    )}
                </Notice>
            )}

            {isScheduled && (
                <Notice tone="info">
                    {campaign?.automation_id
                        ? t`This automated email is scheduled, so it can't be edited.`
                        : t`This campaign is scheduled, so it can't be edited. Move it back to draft to change it.`}
                </Notice>
            )}

            {campaign && isCancelled && (
                <Notice>
                    {campaign.sent_at
                        ? t`This campaign was stopped before it finished. Anyone who hadn't received it yet was skipped.`
                        : t`This campaign was cancelled before it went out, so nobody received it.`}
                </Notice>
            )}

            {isRestricted && (
                <Notice tone="info">
                    {isEditable
                        ? t`Some of this campaign's audience rules filter by spend, so they're hidden from you. You can edit the message, but only a member who can view financials can change who receives it.`
                        : t`Some of this campaign's audience rules filter by spend, so they're hidden from you.`}
                </Notice>
            )}
        </>
    );
};
