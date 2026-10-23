import {useState} from "react";
import {Button, Checkbox, Stack, Text, Textarea} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {Modal, ModalFooter} from "../../../../common/Modal";
import {useAdminSuspendAccount} from "../../../../../mutations/useAdminSuspendAccount";
import {showError, showSuccess} from "../../../../../utilites/notifications";
import {AdminAccountDetail} from "../../../../../api/admin.client";

interface SuspendAccountModalProps {
    account: AdminAccountDetail;
    onClose: () => void;
}

export const SuspendAccountModal = ({account, onClose}: SuspendAccountModalProps) => {
    const [reason, setReason] = useState('');
    const [notifyAccountOwner, setNotifyAccountOwner] = useState(false);
    const suspendMutation = useAdminSuspendAccount(account.id);

    const handleSubmit = () => {
        suspendMutation.mutate({
            reason: reason.trim() || undefined,
            notify_account_owner: notifyAccountOwner,
        }, {
            onSuccess: () => {
                showSuccess(t`Account suspended`);
                onClose();
            },
            onError: (error: any) => showError(
                error?.response?.data?.message ?? t`Failed to suspend account`
            ),
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Suspend ${account.name}`} size="md">
            <Stack gap="md">
                <Text size="sm">
                    <Trans>
                        All live events and organizer pages are moved to draft, and nobody on the account can sign in
                        or use its API keys until it is unsuspended. Unsuspending does not republish anything.
                    </Trans>
                </Text>

                <Textarea
                    label={t`Reason`}
                    description={notifyAccountOwner
                        ? t`Included in the email to the account owner.`
                        : t`Optional, shown to admins on this page.`}
                    value={reason}
                    onChange={(e) => setReason(e.currentTarget.value)}
                    autosize
                    minRows={2}
                    maxLength={1000}
                />

                <Checkbox
                    label={t`Email the account owner`}
                    description={t`Let them know the account has been suspended.`}
                    checked={notifyAccountOwner}
                    onChange={(e) => setNotifyAccountOwner(e.currentTarget.checked)}
                    data-testid="admin-suspend-account-notify-checkbox"
                />

                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        color="red"
                        onClick={handleSubmit}
                        loading={suspendMutation.isPending}
                        data-testid="admin-suspend-account-confirm-button"
                    >
                        {t`Suspend account`}
                    </Button>
                </ModalFooter>
            </Stack>
        </Modal>
    );
};
