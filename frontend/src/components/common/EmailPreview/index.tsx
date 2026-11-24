import {ReactNode, useState} from "react";
import {t} from "@lingui/macro";
import {Avatar, SegmentedControl} from "@mantine/core";
import {IconDeviceDesktop, IconDeviceMobile} from "@tabler/icons-react";
import classNames from "classnames";
import classes from "./EmailPreview.module.scss";

interface EmailPreviewProps {
    label: string;
    fromName: string;
    subject: string;
    preheader?: string | null;
    html: string | undefined;
    isError: boolean;
    controls?: ReactNode;
    banner?: ReactNode;
    className?: string;
    testId?: string;
}

export const EmailPreview = ({
    label,
    fromName,
    subject,
    preheader,
    html,
    isError,
    controls,
    banner,
    className,
    testId = 'email-preview',
}: EmailPreviewProps) => {
    const [device, setDevice] = useState<'desktop' | 'phone'>('desktop');

    return (
        <div className={classNames(classes.preview, className)}>
            <div className={classes.bar}>
                <span>{label}</span>
                <div className={classes.controls}>
                    {controls}
                    <SegmentedControl
                        className={classes.deviceSwitch}
                        size="xs"
                        value={device}
                        onChange={value => setDevice(value === 'phone' ? 'phone' : 'desktop')}
                        data={[
                            {value: 'desktop', label: <IconDeviceDesktop size={14} aria-label={t`Desktop`}/>},
                            {value: 'phone', label: <IconDeviceMobile size={14} aria-label={t`Phone`}/>},
                        ]}
                    />
                </div>
            </div>
            <div className={classes.inbox}>
                <Avatar size={32} radius="xl" color="primary">{fromName.slice(0, 1).toUpperCase()}</Avatar>
                <span className={classes.inboxText} data-testid={`${testId}-inbox`}>
                    <span>{fromName}</span>
                    <strong>{subject || t`Your subject line`}</strong>
                    {preheader && <span className={classes.inboxPreheader}>{preheader}</span>}
                </span>
            </div>
            {banner && <div className={classes.banner}>{banner}</div>}
            <div className={classes.stage}>
                {html ? (
                    <iframe
                        className={classes.frame}
                        data-device={device}
                        title={t`Email preview`}
                        sandbox=""
                        srcDoc={html}
                        data-testid={testId}
                    />
                ) : (
                    <div className={classes.empty}>
                        {isError ? t`We couldn't load the preview. Please try again.` : t`Loading preview…`}
                    </div>
                )}
            </div>
        </div>
    );
};
