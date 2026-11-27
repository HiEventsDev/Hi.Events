import {t} from "@lingui/macro";
import {UnstyledButton} from "@mantine/core";
import {TicketResaleState} from "../../../types.ts";
import {prettyDate} from "../../../utilites/dates.ts";
import classes from "./TicketResale.module.scss";

interface TicketResaleListedProps {
    resale: TicketResaleState;
    timezone: string;
    onWithdraw: () => void;
}

const statusLine = (resale: TicketResaleState, timezone: string): string => {
    if (!resale.held_until) {
        return t`This ticket can't be used while it's listed.`;
    }

    const deadline = prettyDate(resale.held_until, timezone);

    return resale.withdrawal_requested
        ? t`A buyer is checking out. If they don't finish by ${deadline}, your listing will be withdrawn.`
        : t`A buyer is checking out. If they don't finish by ${deadline}, the ticket stays listed.`;
};

export const TicketResaleListed = ({resale, timezone, onWithdraw}: TicketResaleListedProps) => {
    const cutoff = resale.cutoff ? prettyDate(resale.cutoff, timezone) : null;

    return (
        <div className={classes.listed} data-testid="ticket-resale-listed">
            <span>
                {statusLine(resale, timezone)}
                {cutoff && <> {t`Resale closes ${cutoff}.`}</>}
            </span>
            {resale.withdrawal_requested ? (
                <span className={classes.withdrawalRequested}>{t`Withdrawal requested`}</span>
            ) : (
                <UnstyledButton className={classes.withdraw} onClick={onWithdraw} data-testid="ticket-resale-withdraw-button">
                    {t`Withdraw listing`}
                </UnstyledButton>
            )}
        </div>
    );
};
