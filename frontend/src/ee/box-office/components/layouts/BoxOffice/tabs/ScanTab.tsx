import {useState} from "react";
import {SegmentedControl} from "@mantine/core";
import {t} from "@lingui/macro";
import {BoxOfficePublic, BoxOfficeSession, EventType} from "../../../../../../types.ts";
import {useGetCheckInListPublic} from "../../../../../../queries/useGetCheckInListPublic.ts";
import {useCheckInController} from "../../../../../../hooks/useCheckInController.tsx";
import {ScanTab as CheckInScanTab} from "../../../../../../components/layouts/CheckIn/tabs/ScanTab.tsx";
import {SearchTab} from "../../../../../../components/layouts/CheckIn/tabs/SearchTab.tsx";
import {CheckInModals} from "../../../../../../components/layouts/CheckIn/CheckInModals.tsx";
import classes from "../BoxOffice.module.scss";

interface ScanTabProps {
    boxOffice: BoxOfficePublic;
    session: BoxOfficeSession;
}

export const ScanTab = ({boxOffice, session}: ScanTabProps) => {
    const [mode, setMode] = useState<'scan' | 'search'>('scan');
    const checkInListShortId = session.check_in_list_short_id ?? undefined;
    const checkInListQuery = useGetCheckInListPublic(checkInListShortId);
    const checkInList = checkInListQuery.data?.data;

    const controller = useCheckInController({
        checkInListShortId,
        checkInList,
        hidListeningEnabled: mode === 'scan',
        occurrenceOverride: session.event_occurrence?.id ? Number(session.event_occurrence.id) : null,
    });

    if (!session.check_in_available) {
        return <div className={classes.splash}>{session.check_in_unavailable_reason ?? t`Check-in is not available`}</div>;
    }

    return (
        <>
            <div style={{padding: '10px 12px 0'}}>
                <SegmentedControl
                    fullWidth
                    data-testid="box-office-scan-mode"
                    value={mode}
                    onChange={(value) => setMode(value as 'scan' | 'search')}
                    data={[{label: t`Scan`, value: 'scan'}, {label: t`Search`, value: 'search'}]}
                />
            </div>
            {mode === 'scan' ? (
                <CheckInScanTab
                    mode={controller.scanMode}
                    onModeChange={controller.setScanMode}
                    hidPageHasFocus={controller.pageHasFocus}
                    hidBuffer={controller.hidBuffer}
                    isSoundOn={controller.isSoundOn}
                    onSoundToggle={controller.toggleSound}
                    onAttendeeScanned={controller.handleQrCheckIn}
                    onOpenRecentScan={controller.setDetailAttendeePublicId}
                    recentScans={controller.recentScans}
                />
            ) : (
                <SearchTab
                    attendees={controller.attendees}
                    products={controller.products}
                    searchQuery={controller.searchQuery}
                    onSearchChange={controller.setSearchQuery}
                    onCheckInToggle={controller.handleCheckInToggle}
                    onOpenDetail={controller.setDetailAttendeePublicId}
                    isLoading={controller.isAttendeesLoading}
                    isCheckInPending={controller.isCheckInPending}
                    isDeletePending={controller.isDeletePending}
                    allowOrdersAwaitingOfflinePaymentToCheckIn={controller.allowOrdersAwaitingOfflinePaymentToCheckIn}
                    eventType={boxOffice.event.type as EventType}
                    timezone={boxOffice.event.timezone}
                    showRowOccurrences={false}
                />
            )}
            <CheckInModals
                controller={controller}
                checkInListShortId={checkInListShortId}
                eventType={boxOffice.event.type as EventType}
                timezone={boxOffice.event.timezone}
            />
        </>
    );
};
