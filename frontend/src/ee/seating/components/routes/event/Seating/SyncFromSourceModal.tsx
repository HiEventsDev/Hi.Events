import {Button, List, Modal, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {IdParam} from "../../../../../../types.ts";
import {SeatMapDiff} from "../../../../api/seat-map.client.ts";
import {useUpdateEventSeatMap} from "../../../../mutations/useUpdateEventSeatMap.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import classes from "./Seating.module.scss";

interface SyncFromSourceModalProps {
    eventId: IdParam;
    diff: SeatMapDiff | null;
    onClose: () => void;
}

export const SyncFromSourceModal = ({eventId, diff, onClose}: SyncFromSourceModalProps) => {
    const mutation = useUpdateEventSeatMap(eventId);

    const apply = () => mutation.mutate({action: 'sync'}, {
        onSuccess: () => {
            showSuccess(t`Seat map updated`);
            onClose();
        },
        onError: error => showError(firstApiError(error, t`The seat map could not be updated`)),
    });

    return (
        <Modal opened={diff !== null} onClose={onClose} title={t`Changes from the venue seat map`}>
            {diff && (
                <Stack>
                    <List>
                        <List.Item>{t`${diff.added_seat_count} seats added`}</List.Item>
                        <List.Item>{t`${diff.removed_seat_labels.length} seats removed`}</List.Item>
                        <List.Item>{t`${diff.relabelled_seat_count} seats renamed`}</List.Item>
                        <List.Item>{t`${diff.rebanded_seat_count} seats moved to another band`}</List.Item>
                    </List>
                    {diff.removed_seat_labels.length > 0 && (
                        <Text className={classes.muted}>{diff.removed_seat_labels.slice(0, 20).join(', ')}</Text>
                    )}
                    <Button loading={mutation.isPending} data-testid="seating-sync-apply-button" onClick={apply}>
                        {t`Apply changes`}
                    </Button>
                </Stack>
            )}
        </Modal>
    );
};
