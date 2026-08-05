import {Alert, Button, Modal, Textarea, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useDisclosure} from "@mantine/hooks";
import {IconAlertTriangle, IconTrash} from "@tabler/icons-react";
import {t, Trans} from "@lingui/macro";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {useGetAccountDeletionStatus} from "../../../../../../queries/useGetAccountDeletionStatus.ts";
import {useRequestAccountDeletion} from "../../../../../../mutations/useRequestAccountDeletion.ts";
import {useCancelAccountDeletion} from "../../../../../../mutations/useCancelAccountDeletion.ts";
import {useGetMe} from "../../../../../../queries/useGetMe.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {prettyDate} from "../../../../../../utilites/dates.ts";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {LoadingMask} from "../../../../../common/LoadingMask";
import classes from "./DangerZone.module.scss";

const DangerZone = () => {
    const accountQuery = useGetAccount();
    const deletionStatusQuery = useGetAccountDeletionStatus();
    const {data: me} = useGetMe();
    const requestDeletionMutation = useRequestAccountDeletion();
    const cancelDeletionMutation = useCancelAccountDeletion();
    const formErrorHandler = useFormErrorResponseHandler();
    const [modalOpened, {open: openModal, close: closeModal}] = useDisclosure(false);

    const form = useForm({
        initialValues: {
            confirmation: '',
            reason: '',
        }
    });

    const account = accountQuery.data;
    const deletionStatus = deletionStatusQuery.data;
    const isOwner = !!me?.is_account_owner;

    if (!account || !deletionStatus) {
        return <LoadingMask/>;
    }

    const pendingRequest = deletionStatus.deletion_request;
    const willBeAnonymized = deletionStatus.expected_outcome === 'ANONYMIZE';

    const handleRequestDeletion = (values: { confirmation: string; reason: string }) => {
        requestDeletionMutation.mutate({
            confirmation: values.confirmation,
            reason: values.reason || undefined,
        }, {
            onSuccess: () => {
                closeModal();
                showSuccess(t`Your account is now scheduled for deletion.`);
            },
            onError: (error: any) => {
                if (error?.response?.status === 409) {
                    closeModal();
                    showError(error.response.data.message);
                    return;
                }
                formErrorHandler(form, error);
            }
        });
    };

    const handleCancelDeletion = () => {
        cancelDeletionMutation.mutate(undefined, {
            onSuccess: () => {
                showSuccess(t`Account deletion has been cancelled.`);
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Something went wrong. Please try again.`);
            }
        });
    };

    return (
        <>
            <HeadingCard
                heading={t`Danger Zone`}
                subHeading={t`Permanently delete this account and its data.`}
            />

            <Card className={classes.card}>
                {pendingRequest && (
                    <Alert
                        color="red"
                        icon={<IconAlertTriangle/>}
                        title={t`This account is scheduled for deletion`}
                    >
                        <p>
                            <Trans>
                                Your account has been deactivated and will be permanently deleted
                                on <b>{prettyDate(pendingRequest.scheduled_deletion_at, account.timezone || 'UTC')}</b>.
                            </Trans>
                        </p>
                        <Button
                            color="red"
                            variant="outline"
                            onClick={handleCancelDeletion}
                            loading={cancelDeletionMutation.isPending}
                            data-testid="cancel-account-deletion-button"
                        >
                            {t`Cancel deletion`}
                        </Button>
                    </Alert>
                )}

                {!pendingRequest && !deletionStatus.can_request_deletion && (
                    <Alert color="orange" icon={<IconAlertTriangle/>} title={t`Account deletion is blocked`}>
                        {deletionStatus.cannot_delete_reason}
                    </Alert>
                )}

                {!pendingRequest && deletionStatus.can_request_deletion && (
                    <>
                        <p>
                            <Trans>
                                Deleting your account deactivates it immediately: all published events are
                                unpublished and access is disabled. After a 30-day grace period, the deletion
                                becomes permanent. You can cancel at any time during those 30 days.
                            </Trans>
                        </p>
                        <p>
                            {willBeAnonymized
                                ? <Trans>
                                    Because this account has completed orders, transaction records (amounts,
                                    dates, and invoice details) will be retained in an anonymized form for legal
                                    and tax purposes. All personal information will be permanently removed.
                                </Trans>
                                : <Trans>
                                    This account has no completed orders, so all of its data will be permanently
                                    deleted.
                                </Trans>}
                        </p>
                        {!isOwner && (
                            <Alert color="orange" icon={<IconAlertTriangle/>}>
                                {t`Only the account owner can request account deletion.`}
                            </Alert>
                        )}
                        {isOwner && (
                            <Button
                                color="red"
                                leftSection={<IconTrash size={18}/>}
                                onClick={openModal}
                                data-testid="delete-account-button"
                            >
                                {t`Delete account`}
                            </Button>
                        )}
                    </>
                )}
            </Card>

            <Modal opened={modalOpened} onClose={closeModal} title={t`Delete account`}>
                <form onSubmit={form.onSubmit(handleRequestDeletion)}>
                    <p className={classes.modalText}>
                        <Trans>
                            This will deactivate your account immediately and permanently delete it after 30
                            days. To confirm, type your account name: <b>{account.name}</b>
                        </Trans>
                    </p>
                    <TextInput
                        {...form.getInputProps('confirmation')}
                        label={t`Account name`}
                        placeholder={account.name}
                        required
                    />
                    <Textarea
                        {...form.getInputProps('reason')}
                        label={t`Reason (optional)`}
                        placeholder={t`Help us improve by telling us why you're leaving`}
                        mt="md"
                    />
                    <Button
                        type="submit"
                        color="red"
                        fullWidth
                        mt="lg"
                        disabled={form.values.confirmation.trim().toLowerCase() !== account.name.trim().toLowerCase()}
                        loading={requestDeletionMutation.isPending}
                        data-testid="confirm-account-deletion-button"
                    >
                        {t`Permanently delete this account`}
                    </Button>
                </form>
            </Modal>
        </>
    );
};

export default DangerZone;
