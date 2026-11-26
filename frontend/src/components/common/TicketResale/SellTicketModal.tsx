import {t} from "@lingui/macro";
import {Button, Skeleton, Text} from "@mantine/core";
import {IconArrowBackUp, IconCalendarTime, IconInfoCircle, IconPackage, IconTicketOff} from "@tabler/icons-react";
import {Modal, ModalFooter} from "../Modal";
import {Attendee, TicketResaleEstimate} from "../../../types.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {prettyDate} from "../../../utilites/dates.ts";
import classes from "./TicketResale.module.scss";

interface SellTicketModalProps {
    attendee: Attendee;
    ticketTitle?: string;
    timezone: string;
    estimate?: TicketResaleEstimate;
    hasFailedToLoad: boolean;
    isSubmitting: boolean;
    onClose: () => void;
    onConfirm: () => void;
}

const ResaleBreakdown = ({estimate, timezone}: { estimate: TicketResaleEstimate; timezone: string }) => {
    const cutoff = prettyDate(estimate.cutoff, timezone);
    const addOns = estimate.add_ons.join(', ');
    const fees = formatCurrency(estimate.estimated_fees > 0 ? -estimate.estimated_fees : 0, estimate.currency);

    return (
        <>
            <dl className={classes.breakdown}>
                <div className={classes.row}>
                    <dt>{t`You paid`}</dt>
                    <dd data-testid="ticket-resale-amount-paid">{formatCurrency(estimate.amount_paid, estimate.currency)}</dd>
                </div>
                <div className={classes.row}>
                    <dt>{t`Estimated fees on the new sale`}</dt>
                    <dd data-testid="ticket-resale-estimated-fees">{fees}</dd>
                </div>
                <div className={classes.total}>
                    <dt>{t`You'll get back about`}</dt>
                    <dd data-testid="ticket-resale-estimated-refund">{formatCurrency(estimate.estimated_refund, estimate.currency)}</dd>
                </div>
            </dl>
            <p className={classes.note}>
                {t`This amount is an estimate. Your refund goes to the card you paid with once someone else buys your ticket. It is only offered after the event's own tickets of this type sell out.`}
            </p>

            <ul className={classes.terms}>
                <li>
                    <IconTicketOff size={18}/>
                    <span>{t`You can't use this ticket while it's listed.`}</span>
                </li>
                <li>
                    <IconArrowBackUp size={18}/>
                    <span>{t`You can withdraw the listing any time before it sells.`}</span>
                </li>
                <li>
                    <IconCalendarTime size={18}/>
                    <span>{t`Resale closes ${cutoff}. If it hasn't sold by then, the ticket is yours to use.`}</span>
                </li>
                {estimate.add_ons.length > 0 && (
                    <li data-testid="ticket-resale-add-ons">
                        <IconPackage size={18}/>
                        <span>{t`Not included: ${addOns}. Add-ons stay with you and are not refunded.`}</span>
                    </li>
                )}
            </ul>
        </>
    );
};

export const SellTicketModal = ({
    attendee,
    ticketTitle,
    timezone,
    estimate,
    hasFailedToLoad,
    isSubmitting,
    onClose,
    onConfirm,
}: SellTicketModalProps) => {
    const canList = !!estimate?.can_list;
    const isLoading = !estimate && !hasFailedToLoad;

    return (
        <Modal opened onClose={onClose} heading={t`Sell this ticket`} size="md">
            <div className={classes.ticketSummary}>
                <Text fw={600}>{attendee.first_name} {attendee.last_name}</Text>
                {ticketTitle && <Text size="sm" c="dimmed">{ticketTitle}</Text>}
            </div>

            {isLoading && <Skeleton height={132} radius="md"/>}

            {hasFailedToLoad && (
                <div className={classes.blocked} role="alert">
                    <IconInfoCircle size={18}/>
                    <span>{t`We could not load the resale details for this ticket. Please try again.`}</span>
                </div>
            )}

            {estimate && !canList && (
                <div className={classes.blocked} data-testid="ticket-resale-blocked">
                    <IconInfoCircle size={18}/>
                    <span>{estimate.block_message ?? t`This ticket cannot be resold.`}</span>
                </div>
            )}

            {estimate && canList && <ResaleBreakdown estimate={estimate} timezone={timezone}/>}

            <ModalFooter>
                {isLoading || canList ? (
                    <>
                        <Button variant="default" onClick={onClose}>{t`Keep my ticket`}</Button>
                        <Button
                            onClick={onConfirm}
                            loading={isSubmitting}
                            disabled={!canList}
                            data-testid="ticket-resale-submit-button"
                        >
                            {t`List ticket for resale`}
                        </Button>
                    </>
                ) : (
                    <Button variant="default" onClick={onClose}>{t`Close`}</Button>
                )}
            </ModalFooter>
        </Modal>
    );
};
