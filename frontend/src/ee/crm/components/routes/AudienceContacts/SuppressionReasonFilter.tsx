import {plural, t} from "@lingui/macro";
import {Anchor, SegmentedControl, Select} from "@mantine/core";
import {CrmSuppressionReason, CrmSuppressionSummary} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";
import {SUPPRESSION_REASONS, suppressionReasonLabel} from "../../../utilites/suppression.ts";
import classes from "./Contacts.module.scss";

const SUMMARY_KEYS: Record<CrmSuppressionReason, 'unsubscribed' | 'bounced' | 'complained'> = {
    UNSUBSCRIBED: 'unsubscribed',
    BOUNCED: 'bounced',
    COMPLAINED: 'complained',
};

interface SuppressionReasonFilterProps {
    reason: CrmSuppressionReason | null;
    summary: CrmSuppressionSummary | undefined;
    isCompact: boolean;
    onChange: (reason: string | null) => void;
    onCheckAddress: () => void;
}

export const SuppressionReasonFilter = ({reason, summary, isCompact, onChange, onCheckAddress}: SuppressionReasonFilterProps) => {
    const options = [
        {value: 'all', label: t`Every reason`},
        ...SUPPRESSION_REASONS.map(value => ({
            value,
            label: summary ? `${suppressionReasonLabel(value)} · ${formatCount(summary[SUMMARY_KEYS[value]])}` : suppressionReasonLabel(value),
        })),
    ];
    const change = (value: string | null) => onChange(!value || value === 'all' ? null : value);

    return (
        <div className={classes.reasonFilter} data-testid="audience-suppression-counts">
            {isCompact ? (
                <Select
                    aria-label={t`Reason`}
                    data={options}
                    value={reason ?? 'all'}
                    onChange={change}
                    allowDeselect={false}
                />
            ) : (
                <SegmentedControl size="xs" value={reason ?? 'all'} onChange={change} data={options}/>
            )}
            {summary && summary.deleted > 0 && (
                <span>
                    {plural(summary.deleted, {
                        one: '# deleted contact also stays suppressed.',
                        other: '# deleted contacts also stay suppressed.',
                    })}
                    {' '}
                    <Anchor component="button" type="button" size="xs" onClick={onCheckAddress}>{t`Check an address`}</Anchor>
                </span>
            )}
        </div>
    );
};
