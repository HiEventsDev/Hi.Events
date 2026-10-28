import {t} from "@lingui/macro";
import {Button, CopyButton} from "@mantine/core";
import {IconCheck, IconCopy} from "@tabler/icons-react";
import classes from "./OfflinePaymentDetails.module.scss";

interface DetailRowProps {
    label: string;
    value: string;
    copyValue: string;
    copyLabel: string;
    monospace?: boolean;
    testId: string;
}

const DetailRow = ({label, value, copyValue, copyLabel, monospace, testId}: DetailRowProps) => (
    <div className={classes.row} data-testid={testId}>
        <div className={classes.details}>
            <div className={classes.label}>{label}</div>
            <div className={monospace ? `${classes.value} ${classes.monospace}` : classes.value}>{value}</div>
        </div>
        <CopyButton value={copyValue} timeout={2000}>
            {({copied, copy}) => (
                <Button
                    variant="default"
                    size="xs"
                    onClick={copy}
                    color={copied ? 'teal' : undefined}
                    leftSection={copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                    aria-label={copyLabel}
                >
                    {copied ? t`Copied` : t`Copy`}
                </Button>
            )}
        </CopyButton>
    </div>
);

interface OfflinePaymentDetailsProps {
    amount: string;
    amountToCopy: string;
    reference?: string | null;
}

export const OfflinePaymentDetails = ({amount, amountToCopy, reference}: OfflinePaymentDetailsProps) => (
    <div className={classes.wrapper}>
        <DetailRow
            label={t`Amount due`}
            value={amount}
            copyValue={amountToCopy}
            copyLabel={t`Copy amount due`}
            testId="payment-amount-due"
        />
        {reference && (
            <>
                <DetailRow
                    label={t`Payment reference`}
                    value={reference}
                    copyValue={reference}
                    copyLabel={t`Copy payment reference`}
                    monospace
                    testId="payment-reference"
                />
                <div className={classes.hint}>
                    {t`Include this reference with your payment so the organizer can match it to your order.`}
                </div>
            </>
        )}
    </div>
);
