import {modals} from "@mantine/modals";
import {t} from "@lingui/macro";

export const confirmationDialog = (
    message: string,
    onConfirm: () => void,
    labels?: { confirm: string; cancel: string },
) => {
    modals.openConfirmModal({
        title: message,
        labels: labels || {confirm: t`Confirm`, cancel: t`Cancel`},
        onConfirm: () => onConfirm(),
    });
}