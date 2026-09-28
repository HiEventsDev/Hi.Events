import {t, Trans} from "@lingui/macro";
import {IconCheck, IconX} from "@tabler/icons-react";
import {TicketScanZone, ScanMode} from "../../../common/TicketScanZone";
import classes from "./ScanTab.module.scss";
import {RecentScan} from "../types.ts";

export type {ScanMode};

interface ScanTabProps {
    mode: ScanMode;
    onModeChange: (mode: ScanMode) => void;
    hidPageHasFocus: boolean;
    hidBuffer: string;
    isSoundOn: boolean;
    onSoundToggle: () => void;
    onAttendeeScanned: (attendeePublicId: string) => void;
    onOpenRecentScan?: (attendeePublicId: string) => void;
    recentScans: RecentScan[];
}

const relativeTime = (timestamp: number) => {
    const diffSec = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (diffSec < 5) return t`just now`;
    if (diffSec < 60) return t`${diffSec}s ago`;
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return t`${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    return t`${diffHr}h ago`;
};

export const ScanTab = ({
                            mode,
                            onModeChange,
                            hidPageHasFocus,
                            hidBuffer,
                            isSoundOn,
                            onSoundToggle,
                            onAttendeeScanned,
                            onOpenRecentScan,
                            recentScans,
                        }: ScanTabProps) => {
    return (
        <div className={classes.wrap}>
            <TicketScanZone
                mode={mode}
                onModeChange={onModeChange}
                hidPageHasFocus={hidPageHasFocus}
                hidBuffer={hidBuffer}
                isSoundOn={isSoundOn}
                onSoundToggle={onSoundToggle}
                onCodeScanned={onAttendeeScanned}
                listeningLabel={t`Scan a ticket to check in an attendee`}
                pausedLabel={t`Tap this screen to resume scanning`}
                withSideGutter
            />

            <div className={classes.recentSection}>
                <div className={classes.sectionHeader}>{t`Recent check-ins`}</div>
                {recentScans.length === 0 ? (
                    <div className={classes.empty}>
                        <Trans>Scanned tickets will appear here</Trans>
                    </div>
                ) : (
                    <div className={classes.recentList}>
                        {recentScans.slice(0, 8).map(scan => (
                            <button
                                type="button"
                                key={scan.id}
                                className={`${classes.recentItem} ${scan.status === "error" ? classes.recentError : ""} ${scan.status === "duplicate" ? classes.recentDuplicate : ""}`}
                                onClick={() => onOpenRecentScan?.(scan.code)}
                                disabled={!onOpenRecentScan || !scan.code.startsWith("A-")}
                            >
                                <div className={`${classes.indicator} ${classes[`indicator_${scan.status}`]}`}>
                                    {scan.status === "success" && <IconCheck size={14} stroke={3}/>}
                                    {scan.status === "duplicate" && <IconCheck size={14} stroke={3}/>}
                                    {scan.status === "error" && <IconX size={14} stroke={3}/>}
                                </div>
                                <div className={classes.recentMain}>
                                    <div className={classes.recentName}>{scan.name}</div>
                                    <div className={classes.recentMeta}>
                                        <span className={classes.recentCode}>{scan.code}</span>
                                        <span className={classes.dot}>·</span>
                                        <span>{relativeTime(scan.timestamp)}</span>
                                    </div>
                                </div>
                                {scan.status === "duplicate" && (
                                    <span className={classes.tagWarn}>{t`Already in`}</span>
                                )}
                                {scan.status === "error" && (
                                    <span className={classes.tagError}>{t`Failed`}</span>
                                )}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};
