import {Button, Checkbox} from "@mantine/core";
import {useClipboard} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {IconCheck, IconCopy, IconDownload, IconKey, IconPrinter} from "@tabler/icons-react";
import {getConfig} from "../../../utilites/config.ts";
import classes from "./TwoFactor.module.scss";

interface RecoveryCodesDisplayProps {
    codes: string[];
    email?: string;
    acknowledged?: boolean;
    onAcknowledgedChange?: (acknowledged: boolean) => void;
}

const buildRecoveryCodesText = (codes: string[], email?: string) => {
    const appName = getConfig("VITE_APP_NAME", "Hi.Events");

    return [
        t`${appName} recovery codes`,
        email ?? '',
        '',
        ...codes,
        '',
        t`Each code can be used once to sign in if you lose access to your authenticator app.`,
        t`Generated ${new Date().toLocaleString()}`,
    ].join('\n');
};

export const RecoveryCodesDisplay = ({codes, email, acknowledged, onAcknowledgedChange}: RecoveryCodesDisplayProps) => {
    const clipboard = useClipboard({timeout: 2000});

    const download = () => {
        const blob = new Blob([buildRecoveryCodesText(codes, email)], {type: 'text/plain'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${(getConfig("VITE_APP_NAME", "Hi.Events") ?? "hi-events").toLowerCase().replace(/[^a-z0-9]+/g, '-')}-recovery-codes.txt`;
        link.click();
        URL.revokeObjectURL(url);
    };

    const print = () => {
        const printWindow = window.open('', '_blank', 'width=480,height=640');
        if (!printWindow) {
            return;
        }
        const pre = printWindow.document.createElement('pre');
        pre.style.font = '16px/1.8 ui-monospace, Menlo, monospace';
        pre.style.padding = '24px';
        pre.textContent = buildRecoveryCodesText(codes, email);
        printWindow.document.body.appendChild(pre);
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };

    return (
        <div className={classes.recoveryCodes}>
            <div className={classes.recoveryCodesHeader}>
                <div className={classes.iconBadge}><IconKey size={18}/></div>
                <div>
                    <div className={classes.recoveryCodesTitle}>{t`Save your recovery codes`}</div>
                    <p className={classes.recoveryCodesHint}>
                        {t`If you lose your phone, each of these codes gets you in once. Store them in a password manager or print them and keep them somewhere safe. You won't see them again.`}
                    </p>
                </div>
            </div>

            <ol className={classes.codeGrid} data-testid="two-factor-recovery-codes">
                {codes.map((code) => (
                    <li key={code} className={classes.code}>{code}</li>
                ))}
            </ol>

            <div className={classes.codeActions}>
                <Button
                    variant="light"
                    size="xs"
                    leftSection={clipboard.copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                    onClick={() => clipboard.copy(codes.join('\n'))}
                    color={clipboard.copied ? 'green' : undefined}
                >
                    {clipboard.copied ? t`Copied` : t`Copy all`}
                </Button>
                <Button variant="light" size="xs" leftSection={<IconDownload size={14}/>} onClick={download}>
                    {t`Download`}
                </Button>
                <Button variant="light" size="xs" leftSection={<IconPrinter size={14}/>} onClick={print}>
                    {t`Print`}
                </Button>
            </div>

            {onAcknowledgedChange && (
                <Checkbox
                    className={classes.acknowledge}
                    checked={!!acknowledged}
                    onChange={(event) => onAcknowledgedChange(event.currentTarget.checked)}
                    label={t`I have saved my recovery codes somewhere safe`}
                    data-testid="two-factor-recovery-codes-saved"
                />
            )}
        </div>
    );
};
