import {useCallback, useEffect, useRef, useState} from "react";
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
    IconSettings,
} from "@tabler/icons-react";
import {CheckInController, KioskScanResult} from "../../../hooks/useCheckInController.tsx";
import {CheckInList} from "../../../types.ts";
import {InlineCameraScanner} from "./tabs/InlineCameraScanner.tsx";
import {DeviceSetupModal} from "./DeviceSetupModal.tsx";
import {KioskTicketLookup} from "./KioskTicketLookup.tsx";
import classes from "./KioskCheckIn.module.scss";

const RESET_AFTER_MS = 8000;
const TAP_TO_PRINT_RESET_AFTER_MS = 30000;
const STAFF_CONTROLS_HOLD_MS = 800;
const STAFF_CONTROLS_VISIBLE_MS = 30000;

interface KioskCheckInProps {
    checkInList: CheckInList;
    controller: CheckInController;
    result: KioskScanResult | null;
    onReset: () => void;
}

type BadgeState = 'none' | 'printing' | 'tap' | 'ready' | 'failed';

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
                        {badgeState === 'tap' && <><IconPrinter size={18}/> {t`Tap Print my badge, then Print.`}</>}
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

const useScreenWakeLock = () => {
    useEffect(() => {
        if (typeof navigator === 'undefined' || !navigator.wakeLock) {
            return;
        }
        let sentinel: WakeLockSentinel | null = null;
        const requestWakeLock = () => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            navigator.wakeLock.request('screen')
                .then((lock) => {
                    sentinel = lock;
                })
                .catch(() => undefined);
        };
        requestWakeLock();
        document.addEventListener('visibilitychange', requestWakeLock);
        return () => {
            document.removeEventListener('visibilitychange', requestWakeLock);
            sentinel?.release().catch(() => undefined);
        };
    }, []);
};

const useStaffControls = () => {
    const [isVisible, setIsVisible] = useState(false);
    const holdTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (!isVisible) {
            return;
        }
        const timer = setTimeout(() => setIsVisible(false), STAFF_CONTROLS_VISIBLE_MS);
        return () => clearTimeout(timer);
    }, [isVisible]);

    const cancelHold = () => {
        if (holdTimerRef.current) {
            clearTimeout(holdTimerRef.current);
            holdTimerRef.current = null;
        }
    };

    return {
        isVisible,
        hide: () => setIsVisible(false),
        holdHandlers: {
            onPointerDown: () => {
                cancelHold();
                holdTimerRef.current = setTimeout(() => setIsVisible(true), STAFF_CONTROLS_HOLD_MS);
            },
            onPointerUp: cancelHold,
            onPointerLeave: cancelHold,
            onPointerCancel: cancelHold,
            onContextMenu: (event: { preventDefault: () => void }) => event.preventDefault(),
        },
    };
};

export const KioskCheckIn = ({checkInList, controller, result, onReset}: KioskCheckInProps) => {
    const printer = controller.badgePrinter;
    const [settingsOpen, settingsHandlers] = useDisclosure(false);
    const [hasTappedPrint, setHasTappedPrint] = useState(false);
    const staffControls = useStaffControls();
    const [isFindingTicket, setIsFindingTicket] = useState(false);
    const stopFindingTicket = useCallback(() => setIsFindingTicket(false), []);
    useScreenWakeLock();
    const resultPublicId = result && result.status === 'success' ? result.attendee.public_id : null;
    const isPrinting = resultPublicId !== null && printer.isPrinting(resultPublicId);
    const badgeState: BadgeState = (() => {
        if (!printer.isEnabled || resultPublicId === null) {
            return 'none';
        }
        if (printer.hasFailed(resultPublicId)) {
            return 'failed';
        }
        if (printer.printsFromATap) {
            return hasTappedPrint ? 'ready' : 'tap';
        }
        return isPrinting ? 'printing' : 'ready';
    })();
    const resetAfterMs = badgeState === 'tap' ? TAP_TO_PRINT_RESET_AFTER_MS : RESET_AFTER_MS;
    const isWaitingForPrinter = isPrinting && !printer.printsFromATap;

    useEffect(() => {
        setHasTappedPrint(false);
        if (result) {
            setIsFindingTicket(false);
        }
    }, [result]);

    useEffect(() => {
        if (!result || isWaitingForPrinter) {
            return;
        }
        const timer = setTimeout(onReset, resetAfterMs);
        return () => clearTimeout(timer);
    }, [result, isWaitingForPrinter, resetAfterMs]);

    const handlePrintTap = () => {
        if (resultPublicId === null) {
            return;
        }
        printer.printBadge(resultPublicId);
        setHasTappedPrint(true);
    };

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
                                {badgeState === 'tap' && (
                                    <Button
                                        size="xl"
                                        radius="xl"
                                        color="teal"
                                        className={classes.nextButton}
                                        leftSection={<IconPrinter size={22}/>}
                                        loading={isPrinting}
                                        onClick={handlePrintTap}
                                        data-testid="kiosk-print-badge-button"
                                    >
                                        {t`Print my badge`}
                                    </Button>
                                )}
                                {printer.printsFromATap && badgeState === 'ready' && (
                                    <Button
                                        size="md"
                                        radius="xl"
                                        variant="subtle"
                                        color="gray"
                                        leftSection={<IconPrinter size={18}/>}
                                        onClick={handlePrintTap}
                                        data-testid="kiosk-print-again-button"
                                    >
                                        {t`Print again`}
                                    </Button>
                                )}
                                <Button
                                    size="xl"
                                    radius="xl"
                                    color={result.status === 'success' ? 'teal' : 'dark'}
                                    variant={badgeState === 'tap' ? 'default' : 'filled'}
                                    className={classes.nextButton}
                                    onClick={onReset}
                                    data-testid="kiosk-next-button"
                                >
                                    {result.status === 'success' ? t`Next check-in` : t`Done`}
                                </Button>
                                {!isWaitingForPrinter && (
                                    <div className={classes.countdown} aria-hidden="true">
                                        <span
                                            key={`${JSON.stringify(result)}-${resetAfterMs}`}
                                            style={{animationDuration: `${resetAfterMs}ms`}}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    )
                    : isFindingTicket
                        ? (
                            <KioskTicketLookup
                                checkInListShortId={checkInList.short_id}
                                onTicketChosen={controller.handleQrCheckIn}
                                onCancel={stopFindingTicket}
                            />
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
                                {checkInList.kiosk_lookup_enabled && (
                                    <Button
                                        size="lg"
                                        radius="xl"
                                        variant="default"
                                        className={classes.findTicketButton}
                                        onClick={() => setIsFindingTicket(true)}
                                        data-testid="kiosk-find-ticket-button"
                                    >
                                        {t`Can't find your QR code?`}
                                    </Button>
                                )}
                            </div>
                        )}
            </main>

            <footer className={classes.footer}>
                <span className={classes.listName} {...staffControls.holdHandlers} data-testid="kiosk-staff-controls-trigger">
                    {checkInList.name}
                </span>
                {staffControls.isVisible && (
                    <div className={classes.staffControls} data-testid="kiosk-staff-controls">
                        <div className={classes.footerActions}>
                            <button
                                type="button"
                                className={classes.modeButton}
                                onClick={() => {
                                    staffControls.hide();
                                    settingsHandlers.open();
                                }}
                                data-testid="kiosk-setup-button"
                            >
                                <IconSettings size={14}/> {t`Set up`}
                            </button>
                            <button
                                type="button"
                                className={classes.modeButton}
                                onClick={() => controller.setScanMode(controller.scanMode === 'camera' ? 'usb' : 'camera')}
                                data-testid="kiosk-scan-mode-button"
                            >
                                {controller.scanMode === 'camera'
                                    ? <><IconScan size={14}/> {t`Use a USB scanner`}</>
                                    : <><IconCamera size={14}/> {t`Use the camera`}</>}
                            </button>
                        </div>
                    </div>
                )}
            </footer>
            <DeviceSetupModal printer={printer} isKiosk opened={settingsOpen} onClose={settingsHandlers.close}/>
        </div>
    );
};
