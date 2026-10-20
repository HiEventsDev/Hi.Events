import {useParams} from "react-router";
import {useGetCheckInListPublic} from "../../../queries/useGetCheckInListPublic.ts";
import {useEffect, useState} from "react";
import {useDisclosure, useNetwork} from "@mantine/hooks";
import {EventOccurrenceStatus, EventType} from "../../../types.ts";
import {t, Trans} from "@lingui/macro";
import classes from "./CheckIn.module.scss";
import {ActionIcon} from "@mantine/core";
import {IconCalendarEvent, IconChartBar, IconInfoCircle, IconQrcode, IconSearch, IconWifiOff} from "@tabler/icons-react";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {useGetCheckInListStatsPublic} from "../../../queries/useGetCheckInListStatsPublic.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {Countdown} from "../../common/Countdown";
import Truncate from "../../common/Truncate";
import {isSsr} from "../../../utilites/helpers.ts";
import {CheckInInfoModal} from "../../common/CheckIn/CheckInInfoModal";
import {CheckInDescriptionModal} from "../../common/CheckIn/CheckInDescriptionModal";
import {BottomNav, BottomNavTab} from "./BottomNav.tsx";
import {ScanTab} from "./tabs/ScanTab.tsx";
import {SearchTab} from "./tabs/SearchTab.tsx";
import {StatsTab} from "./tabs/StatsTab.tsx";
import {OccurrenceFilterPill} from "./OccurrenceFilterPill.tsx";
import {CheckInModals} from "./CheckInModals.tsx";
import {useCheckInController} from "../../../hooks/useCheckInController.tsx";
import {useHashTab} from "../../../hooks/useHashTab.ts";

type CheckInTab = "scan" | "search" | "stats";

const CHECK_IN_TABS: readonly CheckInTab[] = ["scan", "search", "stats"];

const CheckIn = () => {
    const networkStatus = useNetwork();
    const {checkInListShortId} = useParams();
    const CheckInListQuery = useGetCheckInListPublic(checkInListShortId);
    const checkInList = CheckInListQuery?.data?.data;
    const event = checkInList?.event;

    const [activeTab, setActiveTab] = useHashTab(CHECK_IN_TABS, "scan");
    const [descriptionModalOpen, setDescriptionModalOpen] = useState(false);
    const [infoModalOpen, infoModalHandlers] = useDisclosure(false, {
        onOpen: () => {
            CheckInListQuery.refetch();
        },
    });

    const controller = useCheckInController({
        checkInListShortId,
        checkInList,
        hidListeningEnabled: activeTab === "scan",
    });

    const progressStatsQuery = useGetCheckInListStatsPublic(
        checkInListShortId,
        !!checkInList?.is_active && !checkInList?.is_expired && controller.showOccurrenceFilter && controller.occurrenceFilter !== null,
        controller.occurrenceFilter,
    );

    useEffect(() => {
        if (isSsr()) return;
        if (!checkInListShortId) return;
        if (!checkInList?.description) return;
        const key = `checkInDescriptionSeen:${checkInListShortId}`;
        if (!localStorage.getItem(key)) {
            setDescriptionModalOpen(true);
        }
    }, [checkInListShortId, checkInList?.description]);

    const dismissDescription = () => {
        setDescriptionModalOpen(false);
        if (!isSsr() && checkInListShortId) {
            localStorage.setItem(`checkInDescriptionSeen:${checkInListShortId}`, "1");
        }
    };

    const tabs: BottomNavTab<CheckInTab>[] = [
        {id: "scan", label: t`Scan`, icon: <IconQrcode size={22} stroke={1.7}/>},
        {id: "search", label: t`Search`, icon: <IconSearch size={20} stroke={1.8}/>},
        {id: "stats", label: t`Stats`, icon: <IconChartBar size={20} stroke={1.8}/>},
    ];

    if (CheckInListQuery.error && (CheckInListQuery.error as any).response?.status === 404) {
        return (
            <NoResultsSplash
                heading={t`Check-in list not found`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={(
                    <>
                        <p>
                            {t`The check-in list you are looking for does not exist.`}
                        </p>
                    </>
                )}
            />);
    }

    if (checkInList?.is_expired) {
        return (
            <NoResultsSplash
                heading={t`Check-in list has expired`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                This check-in list has expired and is no longer available for check-ins.
                            </Trans>
                        </p>
                    </>
                )}
            />);
    }

    if (checkInList?.event_occurrence?.status === EventOccurrenceStatus.CANCELLED) {
        return (
            <NoResultsSplash
                heading={t`Session cancelled`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                This check-in list is scoped to a session that has been cancelled, so it can no longer be used for check-ins.
                            </Trans>
                        </p>
                        <p>
                            <Trans>
                                Create a new check-in list for an active session, or contact the organizer if you think this is a mistake.
                            </Trans>
                        </p>
                    </>
                )}
            />);
    }

    if (checkInList && !checkInList?.is_active) {
        return (
            <NoResultsSplash
                heading={t`Check-in list is not active`}
                imageHref={"/blank-slate/check-in-lists.svg"}
                subHeading={(
                    <>
                        <p>
                            {t`This check-in list is not yet active and is not available for check-ins.`}
                        </p>
                        <p>
                            Check-in list will activate in{" "}<br/>
                            <b>
                                <Countdown
                                    targetDate={checkInList.activates_at as string}
                                    onExpiry={() => CheckInListQuery.refetch()}
                                />
                            </b>
                        </p>
                    </>
                )}
            />);
    }

    const filteredStats = progressStatsQuery.data?.data;
    const totalAttendees = filteredStats?.total_attendees ?? checkInList?.total_attendees ?? 0;
    const checkedInCount = filteredStats?.checked_in_attendees ?? checkInList?.checked_in_attendees ?? 0;

    return (
        <div className={classes.app}>
            <header className={classes.topBar}>
                <div className={classes.topBarMain}>
                    <div className={classes.topLabel}>{t`Check-in`}</div>
                    <div className={classes.topTitle}>
                        <Truncate text={checkInList?.name ?? ""} length={26}/>
                    </div>
                    {checkInList?.event_occurrence && event?.timezone && (
                        <div className={classes.topScope}>
                            <IconCalendarEvent size={12}/>
                            <span>
                                {formatDateWithLocale(checkInList.event_occurrence.start_date, 'shortDate', event.timezone)}
                                {' · '}
                                {formatDateWithLocale(checkInList.event_occurrence.start_date, 'timeOnly', event.timezone)}
                                {checkInList.event_occurrence.label ? ` · ${checkInList.event_occurrence.label}` : ''}
                            </span>
                        </div>
                    )}
                </div>
                <div className={classes.topRight}>
                    {totalAttendees > 0 && (
                        <div className={classes.progressChip} aria-label={t`Check-in progress`}>
                            <span className={classes.progressValue}>{checkedInCount}</span>
                            <span className={classes.progressOf}>/{totalAttendees}</span>
                        </div>
                    )}
                    {!networkStatus.online && (
                        <div className={classes.offlineBadge} aria-label={t`Offline`}>
                            <IconWifiOff size={14}/>
                            <span>{t`Offline`}</span>
                        </div>
                    )}
                    <ActionIcon
                        variant="subtle"
                        color="gray"
                        radius="xl"
                        onClick={() => infoModalHandlers.open()}
                        aria-label={t`Check-in list info`}
                        className={classes.infoBtn}
                    >
                        <IconInfoCircle size={20}/>
                    </ActionIcon>
                </div>
            </header>

            {controller.showOccurrenceFilter && event?.timezone && (
                <div className={classes.occurrenceFilterBar}>
                    <OccurrenceFilterPill
                        occurrences={controller.pillOccurrences ?? []}
                        activeOccurrenceId={controller.occurrenceFilter}
                        timezone={event.timezone}
                        onSelect={controller.setOccurrenceFilter}
                    />
                </div>
            )}

            <main className={classes.content}>
                {activeTab === "scan" && (
                    <ScanTab
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
                )}
                {activeTab === "search" && (
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
                        eventType={event?.type as EventType | undefined}
                        timezone={event?.timezone}
                        showRowOccurrences={controller.showOccurrenceFilter}
                    />
                )}
                {activeTab === "stats" && (
                    <StatsTab
                        checkInListShortId={checkInListShortId}
                        enabled={!!checkInList?.is_active && !checkInList?.is_expired}
                        eventOccurrenceId={controller.activeOccurrenceId}
                    />
                )}
            </main>

            <BottomNav tabs={tabs} active={activeTab} onChange={setActiveTab} ariaLabel={t`Check-in navigation`}/>

            <CheckInInfoModal
                isOpen={infoModalOpen}
                checkInList={checkInList}
                onClose={infoModalHandlers.close}
            />
            <CheckInDescriptionModal
                isOpen={descriptionModalOpen}
                description={checkInList?.description}
                onDismiss={dismissDescription}
            />
            <CheckInModals
                controller={controller}
                checkInListShortId={checkInListShortId}
                eventType={event?.type}
                timezone={event?.timezone}
            />
        </div>
    );
};

export default CheckIn;
