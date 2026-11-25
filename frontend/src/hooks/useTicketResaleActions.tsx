import {t} from "@lingui/macro";
import {useEffect, useState} from "react";
import {TicketResaleLink} from "../api/ticket-resale.client.ts";
import {SellTicketModal} from "../components/common/TicketResale/SellTicketModal.tsx";
import {useListTicketForResale} from "../mutations/useListTicketForResale.ts";
import {useWithdrawTicketResale} from "../mutations/useWithdrawTicketResale.ts";
import {useGetTicketResaleEstimate} from "../queries/useGetTicketResaleEstimate.ts";
import {Attendee} from "../types.ts";
import {confirmationDialog} from "../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../utilites/notifications.tsx";
import {getAttendeeProductTitle} from "../utilites/products.ts";
import {clearSelfServiceSession} from "../utilites/selfServiceSession.ts";
import {useSelfServiceVerification} from "./useSelfServiceVerification.tsx";

const VERIFICATION_REQUIRED = 'VERIFICATION_REQUIRED';

interface OrderLink {
    eventId: string | number;
    orderShortId: string;
}

const isVerificationRequired = (error: any): boolean => error?.response?.data?.error_code === VERIFICATION_REQUIRED;

export const useTicketResaleActions = (orderLink: OrderLink, timezone?: string) => {
    const [selling, setSelling] = useState<Attendee | null>(null);
    const {requireVerification, startVerification, verificationModal} = useSelfServiceVerification(orderLink);
    const listMutation = useListTicketForResale();
    const withdrawMutation = useWithdrawTicketResale();

    const linkFor = (attendee: Attendee): TicketResaleLink => ({
        eventId: orderLink.eventId,
        orderShortId: orderLink.orderShortId,
        attendeeShortId: attendee.short_id,
    });

    const estimateQuery = useGetTicketResaleEstimate(selling ? linkFor(selling) : null);

    const reverifyThen = (error: any, retry: () => void): boolean => {
        if (!isVerificationRequired(error)) {
            return false;
        }

        clearSelfServiceSession(orderLink);
        startVerification(retry);

        return true;
    };

    useEffect(() => {
        if (!selling || !isVerificationRequired(estimateQuery.error)) {
            return;
        }

        const attendee = selling;
        setSelling(null);
        reverifyThen(estimateQuery.error, () => setSelling(attendee));
    }, [estimateQuery.error]);

    const listTicket = (attendee: Attendee) => {
        listMutation.mutate(
            {link: linkFor(attendee)},
            {
                onSuccess: () => {
                    setSelling(null);
                    showSuccess(t`Your ticket is listed for resale. We'll email you if it sells.`);
                },
                onError: (error: any) => {
                    if (reverifyThen(error, () => listTicket(attendee))) {
                        return;
                    }
                    if (error?.response?.status === 429) {
                        showError(t`Too many listings have been started. Please try again later.`);
                        return;
                    }
                    showError(error?.response?.data?.message || t`We could not list this ticket. Please try again.`);
                    estimateQuery.refetch();
                },
            },
        );
    };

    const withdrawListedTicket = (attendee: Attendee) => {
        withdrawMutation.mutate(
            {link: linkFor(attendee)},
            {
                onSuccess: (resale) => showSuccess(resale.status === 'LISTED'
                    ? t`Withdrawal requested. A buyer is checking out, so the listing ends if they don't finish.`
                    : t`Listing withdrawn. Your ticket works again.`),
                onError: (error: any) => {
                    if (reverifyThen(error, () => withdrawListedTicket(attendee))) {
                        return;
                    }
                    showError(error?.response?.data?.message || t`We could not withdraw this listing. Please try again.`);
                },
            },
        );
    };

    const withdrawListing = (attendee: Attendee) => confirmationDialog(
        t`Withdraw this listing?`,
        () => requireVerification(() => withdrawListedTicket(attendee)),
        {
            confirm: t`Withdraw listing`,
            cancel: t`Keep it listed`,
            description: attendee.resale?.held_until
                ? t`A buyer is checking out right now. If they finish paying, the sale goes through. If they don't, the listing ends and your ticket works again.`
                : t`Your ticket comes off sale and works again straight away.`,
        },
    );

    const resaleModals = (
        <>
            {selling && timezone && (
                <SellTicketModal
                    attendee={selling}
                    ticketTitle={selling.product ? getAttendeeProductTitle(selling, selling.product) : undefined}
                    timezone={timezone}
                    estimate={estimateQuery.data}
                    hasFailedToLoad={estimateQuery.isError && !isVerificationRequired(estimateQuery.error)}
                    isSubmitting={listMutation.isPending}
                    onClose={() => setSelling(null)}
                    onConfirm={() => requireVerification(() => listTicket(selling))}
                />
            )}
            {verificationModal}
        </>
    );

    return {
        openSell: (attendee: Attendee) => requireVerification(() => setSelling(attendee)),
        withdrawListing,
        resaleModals,
    };
};
