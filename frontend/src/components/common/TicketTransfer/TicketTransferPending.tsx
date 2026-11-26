import {t} from "@lingui/macro";
import {UnstyledButton} from "@mantine/core";
import {PendingTicketTransfer} from "../../../types.ts";
import {prettyDate} from "../../../utilites/dates.ts";
import classes from "./TicketTransfer.module.scss";

interface TicketTransferPendingProps {
    pending: PendingTicketTransfer;
    timezone: string;
    onCancel?: () => void;
}

export const TicketTransferPending = ({pending, timezone, onCancel}: TicketTransferPendingProps) => {
    const recipient = pending.recipient_email;
    const deadline = prettyDate(pending.expires_at, timezone);

    return (
        <div className={classes.pending} data-testid="ticket-transfer-pending">
            <span>{t`Transfer waiting for ${recipient} to accept. They have until ${deadline}. Your ticket still works until then.`}</span>
            {onCancel && (
                <UnstyledButton className={classes.pendingCancel} onClick={onCancel} data-testid="ticket-transfer-cancel-button">
                    {t`Cancel transfer`}
                </UnstyledButton>
            )}
        </div>
    );
};
