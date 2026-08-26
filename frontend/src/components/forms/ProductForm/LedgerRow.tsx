import {ReactNode} from "react";
import {Collapse} from "@mantine/core";
import {IconChevronDown} from "@tabler/icons-react";
import classNames from "classnames";
import {RowSummary} from "./ledgerSummaries.ts";
import classes from "./ProductForm.module.scss";

export type LedgerRowId =
    | 'description'
    | 'sale-window'
    | 'event-page'
    | 'waitlist'
    | 'taxes'
    | 'order-limits'
    | 'addons'
    | 'highlight'
    | 'access';

interface LedgerRowProps {
    id: LedgerRowId;
    icon: ReactNode;
    label: string;
    summary: RowSummary;
    opened: boolean;
    onToggle: (id: LedgerRowId) => void;
    children: ReactNode;
}

export const LedgerRow = ({id, icon, label, summary, opened, onToggle, children}: LedgerRowProps) => {
    const contentId = `product-ledger-content-${id}`;

    return (
        <div className={classes.ledgerRow} id={`product-ledger-row-${id}`}>
            <button
                type="button"
                className={classes.ledgerRowButton}
                aria-expanded={opened}
                aria-controls={contentId}
                data-testid={`product-ledger-${id}`}
                onClick={() => onToggle(id)}
            >
                <span className={classes.ledgerRowIcon}>{icon}</span>
                <span className={classes.ledgerRowLabel}>{label}</span>
                {!opened && (
                    <span className={classNames(classes.ledgerRowSummary, summary.emphasized && classes.emphasized)}>
                        {summary.text}
                    </span>
                )}
                <IconChevronDown
                    size={16}
                    className={classNames(classes.ledgerRowChevron, opened && classes.open)}
                />
            </button>
            <Collapse expanded={opened}>
                <div id={contentId} className={classes.ledgerRowContent}>
                    {children}
                </div>
            </Collapse>
        </div>
    );
};
