import {useEffect} from "react";
import {NavLink, useLocation} from "react-router";
import {Anchor, Button, Textarea, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useDisclosure} from "@mantine/hooks";
import {t, Trans} from "@lingui/macro";
import {IconAlertTriangle, IconArrowRight} from "@tabler/icons-react";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useGetAccountDeletionStatus} from "../../../../queries/useGetAccountDeletionStatus.ts";
import {useRequestAccountDeletion} from "../../../../mutations/useRequestAccountDeletion.ts";
import {useCancelAccountDeletion} from "../../../../mutations/useCancelAccountDeletion.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {prettyDate} from "../../../../utilites/dates.ts";
import {SettingsRow, SettingsSection} from "../../../common/SettingsPage";
import {Modal, ModalFooter} from "../../../common/Modal";
import classes from "./DeleteAccountSection.module.scss";

const DELETE_ACCOUNT_SECTION_ID = 'delete-account';

export const DeleteAccountSection = () => {
    const location = useLocation();
    const {data: account} = useGetAccount();
    const {data: deletionStatus} = useGetAccountDeletionStatus();
    const {data: me} = useGetMe();
    const requestDeletionMutation = useRequestAccountDeletion();
    const cancelDeletionMutation = useCancelAccountDeletion();
    const formErrorHandler = useFormErrorResponseHandler();
    const [modalOpened, {open: openModal, close: closeModal}] = useDisclosure(false);

    const form = useForm({
        initialValues: {
            confirmation: '',
            reason: '',
        },
    });

    const isLoaded = !!account && !!deletionStatus;

    useEffect(() => {
        if (isLoaded && location.hash === `#${DELETE_ACCOUNT_SECTION_ID}`) {
            document.getElementById(DELETE_ACCOUNT_SECTION_ID)?.scrollIntoView({block: 'start'});
        }
    }, [isLoaded, location.hash, location.key]);

    if (!account || !deletionStatus) {
        return null;
    }

    const pendingRequest = deletionStatus.deletion_request;
    const isOwner = !!me?.is_account_owner;
    const confirmationMatches = form.values.confirmation.trim().toLowerCase() === account.name.trim().toLowerCase();

    const handleRequestDeletion = (values: typeof form.values) => {
        requestDeletionMutation.mutate({
            confirmation: values.confirmation,
            reason: values.reason || undefined,
        }, {
            onSuccess: () => {
                closeModal();
                form.reset();
                showSuccess(t`Your account is now scheduled for deletion.`);
            },
            onError: (error: any) => {
                if (error?.response?.status === 409) {
                    closeModal();
                    showError(error.response.data.message);
                    return;
                }
                formErrorHandler(form, error);
            },
        });
    };

    const handleCancelDeletion = () => {
        cancelDeletionMutation.mutate(undefined, {
            onSuccess: () => showSuccess(t`Account deletion has been cancelled.`),
            onError: (error: any) => showError(error?.response?.data?.message || t`Something went wrong. Please try again.`),
        });
    };

    const renderRow = () => {
        if (pendingRequest) {
            return (
                <SettingsRow
                    label={t`This account is scheduled for deletion`}
                    description={
                        <Trans>
                            Your account has been deactivated and will be permanently deleted
                            on <b>{prettyDate(pendingRequest.scheduled_deletion_at, account.timezone || 'UTC')}</b>.
                        </Trans>
                    }
                >
                    <Button
                        variant="default"
                        onClick={handleCancelDeletion}
                        loading={cancelDeletionMutation.isPending}
                        data-testid="cancel-account-deletion-button"
                    >
                        {t`Cancel deletion`}
                    </Button>
                </SettingsRow>
            );
        }

        const isBlocked = !deletionStatus.can_request_deletion;
        const blockingEvents = deletionStatus.blocking_events;

        return (
            <SettingsRow
                label={t`Delete account`}
                description={isOwner
                    ? t`Unpublishes your events and disables access straight away. Deletion becomes permanent after 30 days unless you cancel.`
                    : t`Only the account owner can request account deletion.`}
                below={isBlocked && (
                    <div className={classes.blocked} data-testid="account-deletion-blocked">
                        <div className={classes.blockedHeader}>
                            <IconAlertTriangle size={16} className={classes.blockedIcon}/>
                            <span className={classes.blockedTitle}>{t`Account deletion is blocked`}</span>
                        </div>
                        {blockingEvents.length > 0 ? (
                            <>
                                <p className={classes.blockedText}>
                                    {t`These upcoming events have completed orders. Cancel and refund them, then come back to delete the account.`}
                                </p>
                                <ul className={classes.blockingEvents}>
                                    {blockingEvents.map(event => (
                                        <li key={event.id} className={classes.blockingEvent}>
                                            <span className={classes.blockingEventTitle}>{event.title}</span>
                                            <Anchor
                                                component={NavLink}
                                                to={`/manage/event/${event.id}/orders`}
                                                size="sm"
                                                className={classes.blockingEventLink}
                                            >
                                                {t`View orders`}
                                                <IconArrowRight size={14}/>
                                            </Anchor>
                                        </li>
                                    ))}
                                </ul>
                            </>
                        ) : (
                            <p className={classes.blockedText}>{deletionStatus.cannot_delete_reason}</p>
                        )}
                    </div>
                )}
            >
                {isOwner && (
                    <Button
                        variant="outline"
                        color="red"
                        onClick={openModal}
                        disabled={isBlocked}
                        data-testid="delete-account-button"
                    >
                        {t`Delete account`}
                    </Button>
                )}
            </SettingsRow>
        );
    };

    return (
        <>
            <SettingsSection id={DELETE_ACCOUNT_SECTION_ID} title={t`Danger zone`} tone="danger">
                {renderRow()}
            </SettingsSection>

            <Modal opened={modalOpened} onClose={closeModal} heading={t`Delete account`} size={520}>
                <form onSubmit={form.onSubmit(handleRequestDeletion)}>
                    <ul className={classes.consequences}>
                        <li>{t`If you cancel within 30 days, access comes back, but your events stay unpublished until you publish them again.`}</li>
                        <li>
                            {deletionStatus.expected_outcome === 'ANONYMIZE'
                                ? t`Order and invoice records are kept in anonymized form for tax purposes. Personal information is removed.`
                                : t`This account has no completed orders, so all of its data will be permanently deleted.`}
                        </li>
                    </ul>
                    <TextInput
                        {...form.getInputProps('confirmation')}
                        label={t`Account name`}
                        description={<Trans>Type <b>{account.name}</b> to confirm.</Trans>}
                        placeholder={account.name}
                        autoComplete="off"
                        data-autofocus
                        required
                    />
                    <Textarea
                        {...form.getInputProps('reason')}
                        label={t`Reason (optional)`}
                        placeholder={t`Help us improve by telling us why you're leaving`}
                        autosize
                        minRows={2}
                    />
                    <ModalFooter>
                        <Button variant="default" onClick={closeModal}>{t`Cancel`}</Button>
                        <Button
                            type="submit"
                            color="red"
                            disabled={!confirmationMatches}
                            loading={requestDeletionMutation.isPending}
                            data-testid="confirm-account-deletion-button"
                        >
                            {t`Delete account`}
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>
        </>
    );
};
