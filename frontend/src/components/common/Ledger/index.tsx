import {ReactNode} from "react";
import {Collapse} from "@mantine/core";
import {IconChevronDown} from "@tabler/icons-react";
import classNames from "classnames";
import classes from "./Ledger.module.scss";

export interface RowSummary {
    text: string;
    emphasized: boolean;
}

export const Ledger = ({heading, children}: { heading: string; children: ReactNode }) => (
    <div className={classes.ledger}>
        <div className={classes.ledgerHeading}>{heading}</div>
        {children}
    </div>
);

interface LedgerRowProps<T extends string> {
    idPrefix: string;
    id: T;
    icon: ReactNode;
    label: string;
    summary: RowSummary;
    opened: boolean;
    onToggle: (id: T) => void;
    children: ReactNode;
}

export const LedgerRow = <T extends string>({idPrefix, id, icon, label, summary, opened, onToggle, children}: LedgerRowProps<T>) => {
    const contentId = `${idPrefix}-content-${id}`;

    return (
        <div className={classes.ledgerRow} id={`${idPrefix}-row-${id}`}>
            <button
                type="button"
                className={classes.ledgerRowButton}
                aria-expanded={opened}
                aria-controls={contentId}
                data-testid={`${idPrefix}-${id}`}
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
