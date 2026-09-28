import {t} from "@lingui/macro";
import {IconCamera, IconScan, IconVolume, IconVolumeOff} from "@tabler/icons-react";
import {ActionIcon} from "@mantine/core";
import {InlineCameraScanner} from "../InlineCameraScanner";
import classes from "./TicketScanZone.module.scss";

export type ScanMode = "usb" | "camera";

interface TicketScanZoneProps {
    mode: ScanMode;
    onModeChange: (mode: ScanMode) => void;
    hidPageHasFocus: boolean;
    hidBuffer: string;
    isSoundOn: boolean;
    onSoundToggle: () => void;
    onCodeScanned: (code: string) => void;
    scannerResetToken?: number;
    listeningLabel: string;
    pausedLabel: string;
    withSideGutter?: boolean;
}

export const TicketScanZone = ({
                                    mode,
                                    onModeChange,
                                    hidPageHasFocus,
                                    hidBuffer,
                                    isSoundOn,
                                    onSoundToggle,
                                    onCodeScanned,
                                    scannerResetToken,
                                    listeningLabel,
                                    pausedLabel,
                                    withSideGutter,
                                }: TicketScanZoneProps) => {
    return (
        <>
            <div className={`${classes.scanArea} ${withSideGutter ? classes.scanAreaGutter : ''}`}>
                {mode === "camera" ? (
                    <InlineCameraScanner onAttendeeScanned={onCodeScanned} clearHandledCodesToken={scannerResetToken}/>
                ) : (
                    <div className={classes.usbPane}>
                        <div className={classes.usbStatusRow}>
                            <span className={`${classes.statusDot} ${hidPageHasFocus ? classes.dotActive : classes.dotPaused}`}/>
                            <span className={classes.statusText}>
                                {hidPageHasFocus ? t`USB scanner listening` : t`USB scanner paused`}
                            </span>
                        </div>
                        <div className={classes.usbInstruction}>
                            {hidPageHasFocus ? listeningLabel : pausedLabel}
                        </div>
                        <div className={classes.buffer}>
                            {hidBuffer
                                ? <span className={classes.bufferText}>{hidBuffer}</span>
                                : <span className={classes.bufferPlaceholder}>{t`Waiting for scan…`}</span>}
                        </div>
                    </div>
                )}
            </div>

            <div className={`${classes.toolbar} ${withSideGutter ? classes.toolbarGutter : ''}`}>
                <div className={classes.modeToggle} role="tablist" aria-label={t`Scanner mode`}>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === "usb"}
                        className={`${classes.modeBtn} ${mode === "usb" ? classes.modeActive : ""}`}
                        onClick={() => onModeChange("usb")}
                    >
                        <IconScan size={16}/>
                        <span>{t`USB`}</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === "camera"}
                        className={`${classes.modeBtn} ${mode === "camera" ? classes.modeActive : ""}`}
                        onClick={() => onModeChange("camera")}
                    >
                        <IconCamera size={16}/>
                        <span>{t`Camera`}</span>
                    </button>
                </div>
                <ActionIcon
                    aria-label={isSoundOn ? t`Turn sound off` : t`Turn sound on`}
                    variant="default"
                    size="lg"
                    radius="xl"
                    onClick={onSoundToggle}
                    className={classes.soundBtn}
                >
                    {isSoundOn ? <IconVolume size={18}/> : <IconVolumeOff size={18}/>}
                </ActionIcon>
            </div>
        </>
    );
};
