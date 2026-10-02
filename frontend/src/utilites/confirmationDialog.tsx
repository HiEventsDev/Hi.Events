import {modals} from "@mantine/modals";
import {t} from "@lingui/macro";

interface ConfirmationDialogOptions {
    confirm?: string;
    cancel?: string;
    useCheckoutColors?: boolean;
}

export const confirmationDialog = (
    message: string,
    onConfirm: () => void,
    options?: ConfirmationDialogOptions,
) => {
    const labels = {
        confirm: options?.confirm || t`Confirm`,
        cancel: options?.cancel || t`Cancel`,
    };

    const checkoutStyles = options?.useCheckoutColors ? {
        header: {
            backgroundColor: 'var(--checkout-surface, #FFFFFF)',
        },
        title: {
            color: 'var(--checkout-text-primary, #1a1a1a)',
        },
        content: {
            backgroundColor: 'var(--checkout-surface, #FFFFFF)',
        },
        body: {
            color: 'var(--checkout-text-primary, #1a1a1a)',
        },
    } : undefined;

    modals.openConfirmModal({
        title: message,
        labels,
        styles: checkoutStyles,
        onConfirm: () => onConfirm(),
    });
}

export const confirmationDialogAsync = (
    message: string,
    options?: ConfirmationDialogOptions,
): Promise<boolean> => new Promise(resolve => {
    modals.openConfirmModal({
        title: message,
        labels: {
            confirm: options?.confirm || t`Confirm`,
            cancel: options?.cancel || t`Cancel`,
        },
        onConfirm: () => resolve(true),
        onCancel: () => resolve(false),
        onClose: () => resolve(false),
    });
});
