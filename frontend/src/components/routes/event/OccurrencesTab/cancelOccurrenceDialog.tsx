import {Checkbox, Text} from "@mantine/core";
import {modals} from "@mantine/modals";
import {t} from "@lingui/macro";
import {IdParam} from "../../../../types.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";

type CancelOccurrenceMutation = {
    mutate: (
        variables: { eventId: IdParam; occurrenceId: IdParam; refundOrders: boolean },
        options?: { onSuccess?: () => void; onError?: (error: any) => void },
    ) => void;
};

type OpenCancelOccurrenceDialogArgs = {
    eventId: IdParam;
    occurrenceId: IdParam;
    orderCount?: number;
    mutation: CancelOccurrenceMutation;
    onSuccess?: () => void;
};

export const openCancelOccurrenceDialog = ({
    eventId,
    occurrenceId,
    orderCount = 0,
    mutation,
    onSuccess,
}: OpenCancelOccurrenceDialogArgs) => {
    let refund = false;

    modals.openConfirmModal({
        title: t`Cancel Date`,
        children: (
            <>
                <Text size="sm" mb="md">
                    {t`Are you sure you want to cancel this date? Affected attendees will be notified by email.`}
                </Text>
                {orderCount > 0 && (
                    <Text size="sm" fw={600} c="red" mb="md">
                        {t`This date has ${orderCount} order(s) that will be affected.`}
                    </Text>
                )}
                <Checkbox
                    label={t`Refund all orders for this date`}
                    onChange={(e) => { refund = e.currentTarget.checked; }}
                />
            </>
        ),
        labels: {confirm: t`Cancel Date`, cancel: t`Go Back`},
        confirmProps: {color: 'red'},
        onConfirm: () => {
            mutation.mutate({eventId, occurrenceId, refundOrders: refund}, {
                onSuccess: () => {
                    showSuccess(t`Date cancelled`);
                    onSuccess?.();
                },
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to cancel date`),
            });
        },
    });
};
