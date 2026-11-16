import {ReactNode, useEffect, useState} from "react";
import {Code, List, SegmentedControl, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {CopyButton} from "../../common/CopyButton";
import {buildChromeLaunchCommand, detectDevicePlatform, DevicePlatform} from "../../../utilites/badgePrinting.ts";
import classes from "./CheckIn.module.scss";

interface DeviceSetupGuideProps {
    printsBadges: boolean;
    isKiosk: boolean;
}

const LaunchCommand = ({command}: { command: string }) => (
    <div className={classes.launchCommand}>
        <Code block className={classes.launchCommandCode} data-testid="device-setup-command">{command}</Code>
        <div className={classes.launchCommandCopy}>
            <CopyButton value={command} label={t`Copy command`}/>
        </div>
    </div>
);

const computerSteps = (
    platform: Exclude<DevicePlatform, 'ipad'>,
    command: string,
    printsBadges: boolean,
    isKiosk: boolean,
): ReactNode[] => [
    printsBadges && t`Print a test badge from this page. In Chrome's print window, choose the badge printer and check the paper size matches your labels, then print. Chrome reuses these choices for every badge.`,
    platform === 'mac'
        ? t`Quit Chrome completely with ⌘Q.`
        : t`Close every Chrome window, and quit Chrome from the system tray if it's still running there.`,
    <>
        {platform === 'mac'
            ? t`Open Terminal and run this command:`
            : t`Press Windows+R, paste this command and press Enter:`}
        <LaunchCommand command={command}/>
    </>,
    isKiosk && (platform === 'mac'
        ? t`The page opens full screen without Chrome's controls. Press ⌘Q to close it.`
        : t`The page opens full screen without Chrome's controls. Press Alt+F4 to close it.`),
    printsBadges && t`Print another test badge. It should print straight away, without a print window.`,
].filter(Boolean);

const ipadSteps = (printsBadges: boolean, isKiosk: boolean): ReactNode[] => [
    printsBadges && t`Tap Print a test badge, choose the badge printer and paper size, then tap Print. The iPad remembers both for the next badge.`,
    printsBadges && t`Each badge then takes two taps: Print badge, then Print. Safari adds margins of its own, so badges print a little smaller than from a computer.`,
    isKiosk && t`To keep attendees on this page, turn on Settings › Accessibility › Guided Access, come back here and triple-click the top button.`,
].filter(Boolean);

export const DeviceSetupGuide = ({printsBadges, isKiosk}: DeviceSetupGuideProps) => {
    const [platform, setPlatform] = useState<DevicePlatform>('mac');
    const [pageUrl, setPageUrl] = useState('');

    useEffect(() => {
        setPlatform(detectDevicePlatform());
        setPageUrl(`${window.location.origin}${window.location.pathname}`);
    }, []);

    const steps = platform === 'ipad'
        ? ipadSteps(printsBadges, isKiosk)
        : computerSteps(platform, buildChromeLaunchCommand(platform, pageUrl, {silentPrinting: printsBadges, kiosk: isKiosk}), printsBadges, isKiosk);

    return (
        <section className={classes.setupGuide} data-testid="device-setup-guide">
            <div className={classes.setupGuideHeader}>
                <Text fw={600}>{t`Set up this device`}</Text>
                <SegmentedControl
                    size="xs"
                    value={platform}
                    onChange={(value) => setPlatform(value as DevicePlatform)}
                    data={[
                        {value: 'mac', label: t`Mac`},
                        {value: 'windows', label: t`Windows`},
                        {value: 'ipad', label: t`iPad`},
                    ]}
                    data-testid="device-setup-platform"
                />
            </div>
            <List type="ordered" size="sm" spacing="sm" className={classes.setupSteps}>
                {steps.map((step, index) => <List.Item key={index}>{step}</List.Item>)}
            </List>
            {printsBadges && platform !== 'ipad' && (
                <div className={classes.setupTroubleshooting}>
                    <Text size="xs" c="dimmed">
                        {t`Print window still showing? Chrome was already running when the command ran. Quit it and run the command again.`}
                    </Text>
                    <Text size="xs" c="dimmed">
                        {t`Badges saved as PDF files instead? Chrome's last printer was Save as PDF. Repeat the first step and choose the badge printer.`}
                    </Text>
                    <Text size="xs" c="dimmed">
                        {t`On computers your IT team manages, Chrome's SilentPrintingEnabled policy does the same without the command.`}
                    </Text>
                </div>
            )}
        </section>
    );
};
