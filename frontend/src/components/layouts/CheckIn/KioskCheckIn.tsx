import {useEffect} from "react";
import {useDisclosure} from "@mantine/hooks";
import {Button, Loader} from "@mantine/core";
import {t} from "@lingui/macro";
import {
    IconAlertTriangle,
    IconCamera,
    IconCheck,
    IconInfoCircle,
    IconPrinter,
    IconQrcode,
    IconScan,
} from "@tabler/icons-react";
import {CheckInController, KioskScanResult} from "../../../hooks/useCheckInController.tsx";
import {CheckInList} from "../../../types.ts";
import {InlineCameraScanner} from "./tabs/InlineCameraScanner.tsx";
import {BadgePrinterSettings} from "./BadgePrinterSettings.tsx";
import classes from "./KioskCheckIn.module.scss";

const RESET_AFTER_MS = 8000;

interface KioskCheckInProps {
    checkInList: CheckInList;
    controller: CheckInController;
    result: KioskScanResult | null;
    onReset: () => void;
}

type BadgeState = 'none' | 'printing' | 'ready' | 'failed';

interface ResultPanelProps {
    result: KioskScanResult;
    badgeState: BadgeState;
}

const ResultPanel = ({result, badgeState}: ResultPanelProps) => {
    if (result.status === 'success') {
        return (
            <>
                <div className={classes.resultIcon} data-status="success">
                    <IconCheck size={64} stroke={2.2}/>
                </div>
                <h1 className={classes.resultTitle}>{t`Welcome, ${result.attendee.first_name}!`}</h1>
                <p className={classes.resultText}>{t`You're checked in. Enjoy the event.`}</p>
                {badgeState !== 'none' && (
                    <div className={classes.printStatus} data-status={badgeState} data-testid="kiosk-print-status">
                        {badgeState === 'printing' && <><Loader size={16} color="gray"/> {t`Printing your badge…`}</>}
                        {badgeState === 'ready' && <><IconPrinter size={18}/> {t`Your badge is ready. Collect it from the printer.`}</>}
                        {badgeState === 'failed' && <><IconAlertTriangle size={18}/> {t`We couldn't print your badge. Please see the registration desk.`}</>}
                    </div>
                )}
            </>
        );
    }

    const title = {
        duplicate: result.status === 'duplicate' ? t`You're already checked in, ${result.attendee.first_name}` : '',
        unpaid: t`This order hasn't been paid yet`,
        error: t`We couldn't check you in`,
    }[result.status];

    return (
        <>
            <div className={classes.resultIcon} data-status="problem">
                {result.status === 'duplicate'
                    ? <IconInfoCircle size={60} stroke={1.8}/>
                    : <IconAlertTriangle size={60} stroke={1.8}/>}
            </div>
            <h1 className={classes.resultTitle}>{title}</h1>
            {result.status === 'error' && <p className={classes.resultDetail}>{result.message}</p>}
            <p className={classes.resultText}>
                {result.status === 'duplicate'
                    ? t`Need a new badge? Please see the registration desk.`
                    : t`Please see the registration desk and we'll sort it out.`}
            </p>
        </>
    );
};

export const KioskCheckIn = ({checkInList, controller, result, onReset}: KioskCheckInProps) => {
    const printer = controller.badgePrinter;
    const [settingsOpen, settingsHandlers] = useDisclosure(false);
    const resultPublicId = result && result.status === 'success' ? result.attendee.public_id : null;
    const isPrinting = resultPublicId !== null && printer.isPrinting(resultPublicId);
    const badgeState: BadgeState = !printer.isEnabled || resultPublicId === null
        ? 'none'
        : (isPrinting ? 'printing' : (printer.hasFailed(resultPublicId) ? 'failed' : 'ready'));

    useEffect(() => {
        if (!result || isPrinting) {
            return;
        }
        const timer = setTimeout(onReset, RESET_AFTER_MS);
        return () => clearTimeout(timer);
    }, [result, isPrinting]);

    const event = checkInList.event;
    const logo = event?.images?.find((image) => image.type === 'BADGE_LOGO' || image.type === 'TICKET_LOGO');

    return (
        <div className={classes.kiosk} data-testid="kiosk">
            <header className={classes.header}>
                {logo && <img src={logo.url} alt="" className={classes.logo}/>}
                <div className={classes.eventName}>{event?.title}</div>
            </header>

            <main className={classes.main}>
                {result
                    ? (
                        <div
                            className={classes.result}
                            data-status={result.status === 'success' ? 'success' : 'problem'}
                            data-testid={`kiosk-result-${result.status}`}
                        >
                            <ResultPanel result={result} badgeState={badgeState}/>
                            <div className={classes.actions}>
                                <Button
                                    size="xl"
                                    radius="xl"
                                    color={result.status === 'success' ? 'teal' : 'dark'}
                                    className={classes.nextButton}
                                    onClick={onReset}
                                    data-testid="kiosk-next-button"
                                >
                                    {result.status === 'success' ? t`Next check-in` : t`Done`}
                                </Button>
                                {!isPrinting && (
                                    <div className={classes.countdown} aria-hidden="true">
                                        <span
                                            key={JSON.stringify(result)}
                                            style={{animationDuration: `${RESET_AFTER_MS}ms`}}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    )
                    : (
                        <div className={classes.idle}>
                            {controller.scanMode === 'camera'
                                ? (
                                    <div className={classes.camera}>
                                        <InlineCameraScanner onAttendeeScanned={controller.handleQrCheckIn}/>
                                    </div>
                                )
                                : <IconQrcode size={120} stroke={1.1} className={classes.idleIcon}/>}
                            <h1 className={classes.idleTitle}>{t`Scan your ticket to check in`}</h1>
                            <p className={classes.idleText}>
                                {controller.badgePrinter.isEnabled
                                    ? t`Hold up the QR code from your ticket email and your badge will print.`
                                    : t`Hold up the QR code from your ticket email.`}
                            </p>
                        </div>
                    )}
            </main>

            <footer className={classes.footer}>
                <span>{checkInList.name}</span>
                <div className={classes.footerActions}>
                    {printer.isEnabled && (
                        <button
                            type="button"
                            className={classes.modeButton}
                            onClick={settingsHandlers.open}
                            data-testid="kiosk-printer-settings-button"
                        >
                            <IconPrinter size={14}/> {t`Printer`}
                        </button>
                    )}
                    <button
                        type="button"
                        className={classes.modeButton}
                        onClick={() => controller.setScanMode(controller.scanMode === 'camera' ? 'usb' : 'camera')}
                    >
                        {controller.scanMode === 'camera'
                            ? <><IconScan size={14}/> {t`Use a USB scanner`}</>
                            : <><IconCamera size={14}/> {t`Use the camera`}</>}
                    </button>
                </div>
            </footer>
            {printer.isEnabled && (
                <BadgePrinterSettings printer={printer} opened={settingsOpen} onClose={settingsHandlers.close}/>
            )}
        </div>
    );
};
