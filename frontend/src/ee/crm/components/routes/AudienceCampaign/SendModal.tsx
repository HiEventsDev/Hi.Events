import {useState} from "react";
import {plural, t} from "@lingui/macro";
import {Button, Skeleton} from "@mantine/core";
import {DateTimePicker} from "@mantine/dates";
import {IconAlertTriangle, IconClock, IconSend} from "@tabler/icons-react";
import dayjs from "dayjs";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {IdParam} from "../../../../../types.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {formatDateWithLocale, getDateTimePickerFormat, nowInTimezone} from "../../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useSendCrmCampaign} from "../../../mutations/useSendCrmCampaign.ts";
import {useGetCrmCampaignSendCheck} from "../../../queries/useGetCrmCampaignSendCheck.ts";
import {useGetCrmSmsCampaignPreview} from "../../../queries/useGetCrmSmsCampaignPreview.ts";
import {campaignStatusReason} from "../../../utilites/labels.ts";
import {smsCountryLabel} from "../../../utilites/smsCountries.ts";
import {CrmCampaign, CrmCampaignSendCheck, CrmSmsCountryEstimate, CrmSmsPreview} from "../../../types.ts";
import {SendGateReason} from "../../SendGateReason";
import audienceClasses from "../../Audience.module.scss";
import classes from "./Campaign.module.scss";

interface SendModalProps {
    organizerId: IdParam;
    campaign: CrmCampaign;
    campaignTitle: string;
    timezone: string;
    isScheduling: boolean;
    estimatedSmsParts: number;
    onClose: () => void;
}

const EarlyAccessSummary = ({tickets, requiresReview, isScheduling}: {
    tickets: CrmCampaignSendCheck['early_access_tickets'],
    requiresReview: boolean,
    isScheduling: boolean,
}) => {
    const toHide = tickets.filter(ticket => !ticket.is_hidden).map(ticket => ticket.title).join(', ');
    const all = tickets.map(ticket => ticket.title).join(', ');

    const hidingNote = () => {
        if (requiresReview && isScheduling) {
            return t`Tickets that will be hidden from your event page once this campaign is approved and starts sending at the time you pick: ${toHide}. Until then anyone can still buy them.`;
        }
        if (requiresReview) {
            return t`Tickets that will be hidden from your event page once this campaign is approved and starts sending: ${toHide}. Until then anyone can still buy them.`;
        }
        if (isScheduling) {
            return t`Tickets that will be hidden from your event page when this campaign sends at the time you pick: ${toHide}. Until then anyone can still buy them.`;
        }
        return t`Tickets that will be hidden from your event page as soon as you send: ${toHide}.`;
    };

    return (
        <div className={classes.earlyAccessSummary} data-testid="campaign-send-early-access">
            <strong>{t`Early access`}</strong>
            <span>{t`Only people with this campaign's link can buy: ${all}. The link can be forwarded.`}</span>
            {toHide && <span>{hidingNote()}</span>}
            <span>{t`They stay hidden from everyone else until you make them public again from this campaign.`}</span>
        </div>
    );
};

const SmsCountryTiming = ({country, timezone}: {country: CrmSmsCountryEstimate, timezone: string}) => {
    const name = smsCountryLabel(country.country);
    const resumesAt = formatDateWithLocale(country.next_sending_at, 'shortDateTime', timezone);

    if (country.is_sending_now) {
        return <small>{plural(country.recipients, {one: `${name}: # text goes out now`, other: `${name}: # texts go out now`})}</small>;
    }

    return (
        <small>
            {plural(country.recipients, {
                one: `${name}: # text waits and goes out from ${resumesAt} (${timezone})`,
                other: `${name}: # texts wait and go out from ${resumesAt} (${timezone})`,
            })}
        </small>
    );
};

const SmsFacts = ({estimate, recipients, estimatedParts, timezone, isScheduling}: {
    estimate: CrmSmsPreview['estimate'] | undefined,
    recipients: number,
    estimatedParts: number,
    timezone: string,
    isScheduling: boolean,
}) => {
    const countries = (estimate?.countries ?? []).filter(country => country.recipients > 0);
    const parts = Math.max(estimatedParts, ...countries.map(country => country.parts));
    const totalParts = estimate?.message_parts ?? recipients * parts;

    return (
        <ul className={classes.sendFacts} data-testid="campaign-send-sms-facts">
            <li>
                {plural(totalParts, {one: 'About # message part, billed by Twilio', other: 'About # message parts, billed by Twilio'})}
                <small>
                    {plural(parts, {
                        one: `Each text is about # part. A longer name or link, or the opt-out line in a person's own language, can add parts for some people.`,
                        other: `Each text is about # parts. A longer name or link, or the opt-out line in a person's own language, can add parts for some people.`,
                    })}
                </small>
            </li>
            <li>
                {t`Texts only go out between 9am and 8pm`}
                <small>{t`In countries with several time zones, texts wait until it is between 9am and 8pm in all of them. Texts that wait go out on their own.`}</small>
                {!isScheduling && countries.map(country => <SmsCountryTiming key={country.country} country={country} timezone={timezone}/>)}
            </li>
        </ul>
    );
};

export const SendModal = ({organizerId, campaign, campaignTitle, timezone, isScheduling, estimatedSmsParts, onClose}: SendModalProps) => {
    const sendCampaign = useSendCrmCampaign();
    const checkQuery = useGetCrmCampaignSendCheck(organizerId, campaign.id, isScheduling);
    const check = checkQuery.data;
    const [scheduledAt, setScheduledAt] = useState<string | null>(null);
    const mailableCount = check?.mailable_count ?? 0;
    const requiresReview = !!check?.requires_review;
    const isBlocked = !!check?.blocked_reason;
    const isSms = campaign.channel === 'SMS';
    const smsPreviewQuery = useGetCrmSmsCampaignPreview(organizerId, {
        body: campaign.body,
        event_id: campaign.event_id,
        early_access_product_ids: campaign.early_access_product_ids,
        campaign_id: campaign.id,
    }, isSms);
    const isCountedAtSend = isScheduling && !requiresReview;
    const reviewNotice = campaignStatusReason(check?.review_reason ?? null);

    const handleSend = () => sendCampaign.mutate({
        organizerId,
        campaignId: campaign.id,
        scheduledAt: isScheduling && scheduledAt ? dayjs(scheduledAt).format('YYYY-MM-DD HH:mm:ss') : null,
    }, {
        onSuccess: response => {
            const status = response.data.status;
            if (status === 'PENDING_REVIEW') {
                showSuccess(isScheduling
                    ? t`Sent for review. Once it's approved, it goes out at the time you picked.`
                    : t`Sent for review. We'll send it as soon as it's approved.`);
            } else {
                showSuccess(status === 'SCHEDULED' ? t`Campaign scheduled` : t`Campaign is on its way`);
            }
            onClose();
        },
        onError: error => showError(firstApiError(error, t`We couldn't send this campaign. Please try again.`)),
    });

    const sendLabel = () => {
        if (isBlocked) {
            return t`Can't send yet`;
        }
        if (requiresReview) {
            return t`Submit for review`;
        }
        return isScheduling ? t`Schedule` : plural(mailableCount, {one: 'Send to # person', other: 'Send to # people'});
    };

    const summary = () => {
        if (isSms) {
            return isCountedAtSend
                ? plural(mailableCount, {
                    one: 'This text goes to # person who opted in to texts, in the countries you send to, as of now. The list is worked out again when it sends.',
                    other: 'This text goes to # people who opted in to texts, in the countries you send to, as of now. The list is worked out again when it sends.',
                })
                : plural(mailableCount, {
                    one: 'This text goes to # person who opted in to texts, in the countries you send to. Everyone else you selected is skipped.',
                    other: 'This text goes to # people who opted in to texts, in the countries you send to. Everyone else you selected is skipped.',
                });
        }

        return isCountedAtSend
            ? plural(mailableCount, {
                one: 'This goes to # person who opted in to marketing emails, as of now. The list is worked out again when it sends.',
                other: 'This goes to # people who opted in to marketing emails, as of now. The list is worked out again when it sends.',
            })
            : plural(mailableCount, {
                one: 'This goes to # person who opted in to marketing emails. Everyone else you selected is skipped.',
                other: 'This goes to # people who opted in to marketing emails. Everyone else you selected is skipped.',
            });
    };

    return (
        <Modal opened onClose={onClose} heading={isScheduling ? t`Schedule "${campaignTitle}"` : t`Send "${campaignTitle}"`} size="md">
            {checkQuery.isLoading && <Skeleton height={40} radius="sm"/>}
            {checkQuery.isError && (
                <p className={audienceClasses.panelHint}>{firstApiError(checkQuery.error, t`We couldn't check this campaign. Please try again.`)}</p>
            )}
            {check && isBlocked && (
                <div className={classes.blocked} data-testid="campaign-send-summary">
                    <IconAlertTriangle size={18}/>
                    <span>
                        <SendGateReason
                            organizerId={organizerId}
                            reason={check.blocked_reason}
                            code={check.blocked_reason_code}
                            params={check.blocked_reason_params}
                        />
                    </span>
                </div>
            )}
            {check && !isBlocked && (
                <>
                    <p className={classes.sendSummary} data-testid="campaign-send-summary">{summary()}</p>
                    {isSms && (
                        <SmsFacts
                            estimate={smsPreviewQuery.data?.estimate}
                            recipients={mailableCount}
                            estimatedParts={estimatedSmsParts}
                            timezone={timezone}
                            isScheduling={isScheduling}
                        />
                    )}
                    {check.early_access_tickets.length > 0 && (
                        <EarlyAccessSummary tickets={check.early_access_tickets} requiresReview={requiresReview} isScheduling={isScheduling}/>
                    )}
                    {check.warnings.length > 0 && (
                        <ul className={classes.warnings}>
                            {check.warnings.map(warning => <li key={warning}>{warning}</li>)}
                        </ul>
                    )}
                    {requiresReview && reviewNotice && <p className={audienceClasses.panelHint}>{reviewNotice}</p>}
                </>
            )}
            {isScheduling && !isBlocked && (
                <DateTimePicker
                    mt="md"
                    label={t`Send at (${timezone})`}
                    placeholder={t`Pick a date and time`}
                    valueFormat={getDateTimePickerFormat()}
                    value={scheduledAt}
                    onChange={setScheduledAt}
                    minDate={nowInTimezone(timezone)}
                    nextLabel={t`Next month`}
                    previousLabel={t`Previous month`}
                    submitButtonProps={{'aria-label': t`Confirm`}}
                />
            )}
            <ModalFooter>
                <Button variant="default" onClick={onClose}>{isBlocked ? t`Close` : t`Cancel`}</Button>
                <Button
                    leftSection={isScheduling ? <IconClock size={16}/> : <IconSend size={16}/>}
                    onClick={handleSend}
                    loading={sendCampaign.isPending}
                    disabled={!check || isBlocked || (isScheduling && !scheduledAt)}
                    data-testid="campaign-send-confirm"
                >
                    {sendLabel()}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
