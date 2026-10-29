import {modals} from "@mantine/modals";
import {Text} from "@mantine/core";
import {t} from "@lingui/macro";

interface ConfirmationDialogOptions {
    confirm?: string;
    cancel?: string;
    useCheckoutColors?: boolean;
    destructive?: boolean;
    description?: string;
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
        children: options?.description ? <Text size="sm">{options.description}</Text> : undefined,
        labels,
        styles: checkoutStyles,
        confirmProps: options?.destructive ? {color: 'red'} : undefined,
        closeButtonProps: {'aria-label': t`Close`},
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
        closeButtonProps: {'aria-label': t`Close`},
        onConfirm: () => resolve(true),
        onCancel: () => resolve(false),
        onClose: () => resolve(false),
    });
});
