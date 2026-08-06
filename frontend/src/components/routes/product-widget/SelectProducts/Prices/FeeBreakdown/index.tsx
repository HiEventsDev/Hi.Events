import React, {useState} from "react";
import {Collapse} from "@mantine/core";
import {IconChevronDown} from "@tabler/icons-react";
import classNames from "classnames";
import {formatCurrency} from "../../../../../../utilites/currency.ts";

export interface FeeBreakdownRow {
    label: React.ReactNode;
    amount: number;
    isTotal?: boolean;
}

interface FeeBreakdownProps {
    toggleLabel: React.ReactNode;
    rows: FeeBreakdownRow[];
    currency?: string;
    footnote?: React.ReactNode;
}

export const FeeBreakdown = ({toggleLabel, rows, currency, footnote}: FeeBreakdownProps) => {
    const [opened, setOpened] = useState(false);

    return (
        <>
            <button
                type={'button'}
                className={classNames('hi-fee-toggle', opened && 'open')}
                aria-expanded={opened}
                onClick={() => setOpened(current => !current)}
            >
                {toggleLabel}
                <IconChevronDown size={13} stroke={2} className={opened ? 'open' : ''}/>
            </button>
            <Collapse expanded={opened} transitionDuration={250} className={'hi-fee-breakdown-wrapper'}>
                <div className={'hi-fee-breakdown'}>
                    {rows.map((row, index) => (
                        <div key={index}
                             className={classNames('hi-fee-breakdown-row', row.isTotal && 'hi-fee-breakdown-row-total')}>
                            <span className={'hi-fee-breakdown-label'}>{row.label}</span>
                            <span className={'hi-fee-breakdown-amount'}>{formatCurrency(row.amount, currency)}</span>
                        </div>
                    ))}
                    {footnote && <div className={'hi-fee-breakdown-footnote'}>{footnote}</div>}
                </div>
            </Collapse>
        </>
    );
};
