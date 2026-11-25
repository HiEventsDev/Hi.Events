import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {useState} from "react";
import {TicketLink} from "../api/ticket-transfer.client.ts";
import {TransferTicketFormValues, TransferTicketModal} from "../components/common/TicketTransfer/TransferTicketModal.tsx";
import {useCancelTicketTransfer} from "../mutations/useCancelTicketTransfer.ts";
import {useStartTicketTransfer} from "../mutations/useStartTicketTransfer.ts";
import {Attendee} from "../types.ts";
import {showError, showSuccess} from "../utilites/notifications.tsx";
import {getAttendeeProductTitle} from "../utilites/products.ts";
import {clearSelfServiceSession, SelfServiceLink} from "../utilites/selfServiceSession.ts";
import {useFormErrorResponseHandler} from "./useFormErrorResponseHandler.tsx";
import {useSelfServiceVerification} from "./useSelfServiceVerification.tsx";

const VERIFICATION_REQUIRED = 'VERIFICATION_REQUIRED';

export const useTicketTransferActions = (sessionLink: SelfServiceLink) => {
    const [transferring, setTransferring] = useState<Attendee | null>(null);
    const {requireVerification, startVerification, verificationModal} = useSelfServiceVerification(sessionLink);
    const startMutation = useStartTicketTransfer();
    const cancelMutation = useCancelTicketTransfer();
    const formErrorHandler = useFormErrorResponseHandler();

    const linkFor = (attendee: Attendee): TicketLink => ({
        eventId: sessionLink.eventId,
        orderShortId: sessionLink.orderShortId,
        attendeeShortId: attendee.short_id,
    });

    const reverifyThen = (error: any, retry: () => void): boolean => {
        if (error?.response?.data?.error_code !== VERIFICATION_REQUIRED) {
            return false;
        }

        clearSelfServiceSession(sessionLink);
        startVerification(retry);

        return true;
    };

    const sendTransfer = (attendee: Attendee, values: TransferTicketFormValues, form: UseFormReturnType<TransferTicketFormValues>) => {
        startMutation.mutate(
            {link: linkFor(attendee), recipientEmail: values.recipient_email},
            {
                onSuccess: (pending) => {
                    setTransferring(null);
                    showSuccess(t`We've emailed ${pending.recipient_email} a link to accept this ticket.`);
                },
                onError: (error: any) => {
                    if (reverifyThen(error, () => sendTransfer(attendee, values, form))) {
                        return;
                    }
                    if (error?.response?.status === 429) {
                        showError(t`Too many transfers have been started. Please try again later.`);
                        return;
                    }
                    formErrorHandler(form, error);
                },
            },
        );
    };

    const cancelPendingTransfer = (attendee: Attendee) => {
        cancelMutation.mutate(
            {link: linkFor(attendee)},
            {
                onSuccess: () => showSuccess(t`Transfer cancelled. The ticket is still yours.`),
                onError: (error: any) => {
                    if (reverifyThen(error, () => cancelPendingTransfer(attendee))) {
                        return;
                    }
                    showError(error?.response?.data?.message || t`We could not cancel this transfer. Please try again.`);
                },
            },
        );
    };

    const cancelTransfer = (attendee: Attendee) => {
        if (!window.confirm(t`Cancel this transfer? The link we emailed will stop working.`)) {
            return;
        }

        requireVerification(() => cancelPendingTransfer(attendee));
    };

    const transferModals = (
        <>
            {transferring && (
                <TransferTicketModal
                    attendee={transferring}
                    ticketTitle={transferring.product ? getAttendeeProductTitle(transferring, transferring.product) : undefined}
                    isSubmitting={startMutation.isPending}
                    onClose={() => setTransferring(null)}
                    onSubmit={(values, form) => requireVerification(() => sendTransfer(transferring, values, form))}
                />
            )}
            {verificationModal}
        </>
    );

    return {openTransfer: setTransferring, cancelTransfer, transferModals};
};
