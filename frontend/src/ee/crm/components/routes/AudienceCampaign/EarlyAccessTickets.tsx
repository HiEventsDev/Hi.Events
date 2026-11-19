import {plural, t, Trans} from "@lingui/macro";
import {Button, MultiSelect} from "@mantine/core";
import {IconLockOpen} from "@tabler/icons-react";
import {useQueryClient} from "@tanstack/react-query";
import {GET_EVENT_QUERY_KEY, useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {IdParam} from "../../../../../types.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useEndCrmEarlyAccess} from "../../../mutations/useCrmCampaignTemplateMutations.ts";
import {CrmCampaign} from "../../../types.ts";
import classes from "./Campaign.module.scss";

const EVENT_LINK_PLACEHOLDER = '{{ event_url }}';

const useEventTickets = (eventId: IdParam) => {
    const eventQuery = useGetEvent(eventId);
    const tickets = (eventQuery.data?.product_categories ?? [])
        .flatMap(category => category.products ?? [])
        .filter(product => product.id !== undefined)
        .map(product => ({id: String(product.id), title: product.title, isHidden: !!product.is_hidden_without_promo_code}));

    return {tickets, isLoading: eventQuery.isLoading};
};

export const EarlyAccessTickets = ({eventId, value, onChange, error, needsEventLink}: {
    needsEventLink: boolean,
    eventId: string,
    value: string[],
    onChange: (ids: string[]) => void,
    error?: unknown,
}) => {
    const {tickets, isLoading} = useEventTickets(eventId);
    const chosen = tickets.filter(ticket => value.includes(ticket.id));
    const willHide = chosen.filter(ticket => !ticket.isHidden).map(ticket => ticket.title).join(', ');

    return (
        <div className={classes.earlyAccess}>
            <MultiSelect
                label={t`Early access (optional)`}
                classNames={{label: classes.earlyAccessLabel}}
                placeholder={value.length === 0 ? t`Choose tickets only these recipients can buy` : undefined}
                data={tickets.map(ticket => {
                    const title = ticket.title;
                    return {value: ticket.id, label: ticket.isHidden ? t`${title} (already hidden)` : title};
                })}
                value={value}
                onChange={onChange}
                disabled={isLoading}
                searchable
                clearable
                nothingFoundMessage={t`This event has no tickets yet`}
                error={typeof error === 'string' ? error : undefined}
                data-testid="campaign-early-access"
            />
            {chosen.length > 0 && (
                <ul className={classes.earlyAccessNotes} data-testid="campaign-early-access-notes">
                    <li>{t`Only people with this campaign's link can buy them. The link can be forwarded.`}</li>
                    {needsEventLink && <li><b>{t`Insert ${EVENT_LINK_PLACEHOLDER} into your message, or nobody will get the link.`}</b></li>}
                    {willHide && <li><Trans>Tickets that will be hidden from your event page when you send: <b>{willHide}</b>.</Trans></li>}
                    <li>{t`They stay hidden from everyone else until you make them public again from this campaign.`}</li>
                </ul>
            )}
        </div>
    );
};

export const SentEarlyAccess = ({organizerId, campaign, canRelease}: {
    organizerId: IdParam,
    campaign: CrmCampaign,
    canRelease: boolean,
}) => {
    const queryClient = useQueryClient();
    const endEarlyAccess = useEndCrmEarlyAccess();
    const {tickets} = useEventTickets(campaign.event_id as number);
    const chosen = tickets.filter(ticket => campaign.early_access_product_ids.includes(Number(ticket.id)));
    const hidden = chosen.filter(ticket => ticket.isHidden);
    const titles = chosen.map(ticket => ticket.title).join(', ');
    const hiddenTitles = hidden.map(ticket => ticket.title).join(', ');
    const code = campaign.promo_code;

    const release = () => confirmationDialog(
        plural(hidden.length, {one: 'Make # ticket public?', other: 'Make # tickets public?'}),
        () => endEarlyAccess.mutate({organizerId, campaignId: campaign.id}, {
            onSuccess: () => {
                showSuccess(t`Tickets are public again`);
                return queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY]});
            },
            onError: error => showError(firstApiError(error, t`We couldn't make these tickets public. Please try again.`)),
        }),
        {
            confirm: t`Make public`,
            description: t`Tickets that will show on your event page for everyone: ${hiddenTitles}. The early access link keeps working.`,
        },
    );

    if (!code) {
        return <span className={classes.lineHint}>{t`Early access: ${titles}`}</span>;
    }

    return (
        <div className={classes.sentEarlyAccess}>
            <span className={classes.lineHint}>
                {hidden.length > 0
                    ? t`Early access with code ${code}: ${titles}. Hidden from everyone else.`
                    : t`Early access with code ${code}: ${titles}. Now public.`}
            </span>
            {canRelease && hidden.length > 0 && (
                <Button
                    variant="default"
                    size="sm"
                    leftSection={<IconLockOpen size={14}/>}
                    onClick={release}
                    loading={endEarlyAccess.isPending}
                >
                    {t`Make tickets public`}
                </Button>
            )}
        </div>
    );
};
