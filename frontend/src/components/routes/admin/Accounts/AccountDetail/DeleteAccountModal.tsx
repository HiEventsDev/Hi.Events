import {useState} from "react";
import {Alert, Button, Checkbox, Group, Modal, Stack, Text, Textarea, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {useAdminRequestAccountDeletion} from "../../../../../mutations/useAdminRequestAccountDeletion";
import {showError, showSuccess} from "../../../../../utilites/notifications";
import {AdminAccountDetail} from "../../../../../api/admin.client";

interface DeleteAccountModalProps {
    account: AdminAccountDetail;
    onClose: () => void;
}

export const DeleteAccountModal = ({account, onClose}: DeleteAccountModalProps) => {
    const [reason, setReason] = useState('');
    const [executeImmediately, setExecuteImmediately] = useState(false);
    const [confirmation, setConfirmation] = useState('');
    const requestDeletionMutation = useAdminRequestAccountDeletion(account.id);

    const isConfirmed = confirmation.trim() === account.name.trim();

    const handleSubmit = () => {
        requestDeletionMutation.mutate({
            reason: reason || undefined,
            execute_immediately: executeImmediately,
        }, {
            onSuccess: () => {
                showSuccess(executeImmediately
                    ? t`Account deletion queued`
                    : t`Account scheduled for deletion in 30 days`);
                onClose();
            },
            onError: (error: any) => showError(
                error?.response?.data?.message ?? t`Failed to delete account`
            ),
        });
    };

    return (
        <Modal opened onClose={onClose} title={t`Delete ${account.name}`} size="md">
            <Stack gap="md">
                <Text size="sm">
                    <Trans>
                        Live events are unpublished straight away. Accounts with completed orders are anonymised to keep
                        financial records; all others are permanently deleted. The account owner is emailed.
                    </Trans>
                </Text>

                <Textarea
                    label={t`Reason`}
                    placeholder={t`Optional, kept on the deletion record`}
                    value={reason}
                    onChange={(e) => setReason(e.currentTarget.value)}
                    autosize
                    minRows={2}
                    maxLength={1000}
                />

                <Checkbox
                    label={t`Delete immediately`}
                    description={t`Skip the 30-day grace period. This cannot be undone.`}
                    checked={executeImmediately}
                    onChange={(e) => setExecuteImmediately(e.currentTarget.checked)}
                    data-testid="admin-delete-account-immediately-checkbox"
                />

                {executeImmediately && (
                    <Alert color="red" variant="light">
                        {t`The account will be deleted as soon as the queue picks up the job.`}
                    </Alert>
                )}

                <TextInput
                    label={t`Type the account name to confirm`}
                    placeholder={account.name}
                    value={confirmation}
                    onChange={(e) => setConfirmation(e.currentTarget.value)}
                />

                <Group justify="flex-end">
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        color="red"
                        onClick={handleSubmit}
                        disabled={!isConfirmed}
                        loading={requestDeletionMutation.isPending}
                        data-testid="admin-delete-account-confirm-button"
                    >
                        {executeImmediately ? t`Delete now` : t`Schedule deletion`}
                    </Button>
                </Group>
            </Stack>
        </Modal>
    );
};
