import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Alert, Badge, Loader, Text} from "@mantine/core";
import {GenericModalProps, IdParam, OutgoingMessage} from "../../../types.ts";
import {useGetMessageRecipients} from "../../../queries/useGetMessageRecipients.ts";
import {relativeDate} from "../../../utilites/dates.ts";
import {Pagination} from "../../common/Pagination";
import {useState} from "react";
import classes from "./MessageRecipientsModal.module.scss";

interface MessageRecipientsModalProps extends GenericModalProps {
    eventId: IdParam;
    messageId: IdParam;
}

const deliveryBadge = (recipient: OutgoingMessage): { label: string, color: string } => {
    if (recipient.status === 'FAILED') {
        return {label: t`Failed`, color: 'red'};
    }
    if (recipient.complained_at) {
        return {label: t`Marked as spam`, color: 'orange'};
    }
    if (recipient.bounced_at) {
        return {label: t`Bounced`, color: 'red'};
    }
    if (recipient.delivered_at) {
        return {label: t`Delivered`, color: 'green'};
    }
    return {label: t`Sent`, color: 'blue'};
};

export const MessageRecipientsModal = ({onClose, eventId, messageId}: MessageRecipientsModalProps) => {
    const [page, setPage] = useState(1);
    const recipientsQuery = useGetMessageRecipients(eventId, messageId, {pageNumber: page, perPage: 100});
    const recipients = recipientsQuery.data?.data;
    const pagination = recipientsQuery.data?.meta;
    const total = pagination?.total;

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Recipients`}
        >
            {recipientsQuery.isLoading && (
                <div className={classes.loadingState}>
                    <Loader size="sm"/>
                </div>
            )}

            {!!recipientsQuery.error && (
                <Alert color="red" radius="md">
                    {t`Failed to load recipients`}
                </Alert>
            )}

            {!recipientsQuery.isLoading && !recipientsQuery.error && recipients && recipients.length === 0 && (
                <div className={classes.emptyState}>
                    <Text>{t`No recipients found`}</Text>
                </div>
            )}

            {!recipientsQuery.isLoading && !recipientsQuery.error && recipients && recipients.length > 0 && (
                <>
                    {total !== undefined && (
                        <div className={classes.headerCount}>
                            {total} {total === 1 ? t`recipient` : t`recipients`}
                        </div>
                    )}
                    {recipients.map((recipient) => {
                        const badge = deliveryBadge(recipient);

                        return (
                            <div key={recipient.id} className={classes.recipientRow} data-testid="message-recipient-row">
                                <span className={classes.recipientEmail}>{recipient.recipient}</span>
                                <div className={classes.recipientRight}>
                                    <Badge size="xs" color={badge.color} variant="light" data-testid="message-recipient-status">
                                        {badge.label}
                                    </Badge>
                                    {recipient.created_at && (
                                        <span className={classes.recipientDate}>
                                            {relativeDate(recipient.created_at)}
                                        </span>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    {pagination && Number(pagination.last_page) > 1 && (
                        <Pagination
                            value={page}
                            onChange={setPage}
                            total={Number(pagination.last_page)}
                            size="sm"
                        />
                    )}
                </>
            )}
        </Modal>
    );
};
