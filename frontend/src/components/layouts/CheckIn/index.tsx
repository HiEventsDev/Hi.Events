import {useParams} from "react-router";
import {useGetCheckInListPublic} from "../../../queries/useGetCheckInListPublic.ts";
import {useCallback, useEffect, useRef, useState} from "react";
import {useDebouncedValue, useDisclosure, useNetwork} from "@mantine/hooks";
import {Attendee, EventOccurrenceStatus, EventType, QueryFilters, QueryFilterOperator} from "../../../types.ts";
import {showError, showInfo, showSuccess, showSuccessWithUndo} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {AxiosError} from "axios";
import classes from "./CheckIn.module.scss";
import {ActionIcon} from "@mantine/core";
import {IconCalendarEvent, IconInfoCircle, IconWifiOff} from "@tabler/icons-react";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {useHaptics} from "../../../hooks/useHaptics.ts";
import {useGetCheckInListAttendees} from "../../../queries/useGetCheckInListAttendeesPublic.ts";
import {useGetCheckInListStatsPublic} from "../../../queries/useGetCheckInListStatsPublic.ts";
import {useCreateCheckInPublic} from "../../../mutations/useCreateCheckInPublic.ts";
import {useDeleteCheckInPublic} from "../../../mutations/useDeleteCheckInPublic.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {Countdown} from "../../common/Countdown";
import Truncate from "../../common/Truncate";
import {publicCheckInClient} from "../../../api/check-in.client.ts";
import {isSsr} from "../../../utilites/helpers.ts";
import {CheckInOptionsModal} from "../../common/CheckIn/CheckInOptionsModal";
import {CheckInInfoModal} from "../../common/CheckIn/CheckInInfoModal";
import {CheckInDescriptionModal} from "../../common/CheckIn/CheckInDescriptionModal";
import {BottomNav, CheckInTab} from "./BottomNav.tsx";
import {ScanTab, ScanMode} from "./tabs/ScanTab.tsx";
import {SearchTab} from "./tabs/SearchTab.tsx";
import {StatsTab} from "./tabs/StatsTab.tsx";
import {AttendeeDetailSheet} from "./AttendeeDetailSheet.tsx";
import {OccurrenceFilterPill} from "./OccurrenceFilterPill.tsx";
import {useCheckInOccurrenceFilter} from "../../../hooks/useCheckInOccurrenceFilter.ts";
import {RecentScan, RecentScanStatus} from "./types.ts";

const MAX_RECENT_SCANS = 20;

const CheckIn = () => {
    const networkStatus = useNetwork();
    const {checkInListShortId} = useParams();
    const CheckInListQuery = useGetCheckInListPublic(checkInListShortId);
    const checkInList = CheckInListQuery?.data?.data;
    const event = checkInList?.event;
    const eventSettings = event?.settings;

    const [activeTab, setActiveTab] = useState<CheckInTab>(() => {
        if (isSsr()) return "scan";
        const hash = window.location.hash.replace("#", "");
        if (hash === "search" || hash === "stats" || hash === "scan") return hash;
        return "scan";
    });
    const [descriptionModalOpen, setDescriptionModalOpen] = useState(false);

    useEffect(() => {
        if (isSsr()) return;
        if (window.location.hash !== `#${activeTab}`) {
            window.history.replaceState(null, "", `#${activeTab}`);
        }
    }, [activeTab]);

    useEffect(() => {
        if (isSsr()) return;
        const handleHashChange = () => {
            const hash = window.location.hash.replace("#", "");
            if (hash === "search" || hash === "stats" || hash === "scan") {
                setActiveTab(hash);
            }
        };
        window.addEventListener("hashchange", handleHashChange);
        return () => window.removeEventListener("hashchange", handleHashChange);
    }, []);
    const [scanMode, setScanMode] = useState<ScanMode>(() => {
        if (isSsr()) return "usb";
        const stored = localStorage.getItem("checkInScanMode");
        return stored === "camera" ? "camera" : "usb";
    });
    const [recentScans, setRecentScans] = useState<RecentScan[]>([]);
    const [searchQuery, setSearchQuery] = useState("");
    const [searchQueryDebounced] = useDebouncedValue(searchQuery, 200);
    const [currentBarcode, setCurrentBarcode] = useState("");
    const [pageHasFocus, setPageHasFocus] = useState(true);

    const barcodeTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const isProcessingRef = useRef(false);
    const processedBarcodesRef = useRef<Set<string>>(new Set());
    const lastScanTimeRef = useRef<number>(0);
    const scanSuccessAudioRef = useRef<HTMLAudioElement | null>(null);
    const scanErrorAudioRef = useRef<HTMLAudioElement | null>(null);

    const [isSoundOn, setIsSoundOn] = useState(() => {
        if (isSsr()) return true;
        const storedIsSoundOn = localStorage.getItem("scannerSoundOn");
        return storedIsSoundOn === null ? true : JSON.parse(storedIsSoundOn);
    });
    const [selectedAttendee, setSelectedAttendee] = useState<Attendee | null>(null);
    const [detailAttendeePublicId, setDetailAttendeePublicId] = useState<string | null>(null);
    const [checkInModalOpen, checkInModalHandlers] = useDisclosure(false);
    const haptic = useHaptics();
    const [infoModalOpen, infoModalHandlers] = useDisclosure(false, {
        onOpen: () => {
            CheckInListQuery.refetch();
        },
    });

    const products = checkInList?.products;

    // Prefer the list's unfiltered occurrences (includes past dates for
    // reconciliation) over event.occurrences (future-only, customer-facing).
    const pillOccurrences = checkInList?.event_occurrences ?? event?.occurrences;

    const showOccurrenceFilter =
        event?.type === EventType.RECURRING
        && !checkInList?.event_occurrence_id
        && (pillOccurrences?.length ?? 0) > 0;
    const {occurrenceId: occurrenceFilter, setOccurrenceId: setOccurrenceFilter, didClearStale} =
        useCheckInOccurrenceFilter(checkInListShortId, pillOccurrences);

    useEffect(() => {
        if (didClearStale) {
            showInfo(t`Your saved date filter is no longer available — showing all dates.`);
        }
    }, [didClearStale]);

    const queryFilters: QueryFilters = {
        pageNumber: 1,
        query: searchQueryDebounced,
        perPage: 150,
        filterFields: {
            status: {operator: QueryFilterOperator.Equals, value: "ACTIVE"},
            ...(showOccurrenceFilter && occurrenceFilter !== null
                ? {event_occurrence_id: {operator: QueryFilterOperator.Equals, value: String(occurrenceFilter)}}
                : {}),
        },
    };

    const attendeesQuery = useGetCheckInListAttendees(
        checkInListShortId,
        queryFilters,
        checkInList?.is_active && !checkInList?.is_expired,
    );
    const attendees = attendeesQuery?.data?.data;
    const checkInMutation = useCreateCheckInPublic(queryFilters);
    const deleteCheckInMutation = useDeleteCheckInPublic(queryFilters);
    const areOfflinePaymentsEnabled = eventSettings?.payment_providers?.includes("OFFLINE");
    const allowOrdersAwaitingOfflinePaymentToCheckIn = areOfflinePaymentsEnabled
        && eventSettings?.allow_orders_awaiting_offline_payment_to_check_in;
    const progressStatsQuery = useGetCheckInListStatsPublic(
        checkInListShortId,
        !!checkInList?.is_active && !checkInList?.is_expired && showOccurrenceFilter && occurrenceFilter !== null,
        occurrenceFilter,
    );

    useEffect(() => {
        if (!isSsr()) {
            localStorage.setItem("scannerSoundOn", JSON.stringify(isSoundOn));
        }
    }, [isSoundOn]);

    useEffect(() => {
        if (!isSsr()) {
            localStorage.setItem("checkInScanMode", scanMode);
        }
    }, [scanMode]);

    // Show description on first open; dismissal persists per list short_id.
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

    const playSuccessSound = useCallback(() => {
        if (isSoundOn && scanSuccessAudioRef.current) {
            scanSuccessAudioRef.current.currentTime = 0;
            scanSuccessAudioRef.current.play().catch(() => {
            });
        }
    }, [isSoundOn]);

    const playErrorSound = useCallback(() => {
        if (isSoundOn && scanErrorAudioRef.current) {
            scanErrorAudioRef.current.currentTime = 0;
            scanErrorAudioRef.current.play().catch(() => {
            });
        }
    }, [isSoundOn]);

    const pushRecentScan = useCallback((scan: Omit<RecentScan, "id" | "timestamp">) => {
        setRecentScans(prev => [
            {...scan, id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, timestamp: Date.now()},
            ...prev,
        ].slice(0, MAX_RECENT_SCANS));
    }, []);

    const recordScan = useCallback((attendee: Attendee | null, code: string, status: RecentScanStatus) => {
        const name = attendee
            ? `${attendee.first_name ?? ""} ${attendee.last_name ?? ""}`.trim() || code
            : code;
        pushRecentScan({name, code, status});
    }, [pushRecentScan]);

    const undoCheckIn = useCallback((attendee: Attendee, checkInShortId: string) => {
        deleteCheckInMutation.mutate({
            checkInListShortId: checkInListShortId,
            checkInShortId: checkInShortId,
        }, {
            onSuccess: () => {
                showSuccess(<Trans>Check-in for {attendee.first_name} was undone</Trans>);
                playSuccessSound();
                haptic("tap");
            },
            onError: () => {
                showError(t`Unable to undo check-in`);
                playErrorSound();
                haptic("error");
            },
        });
    }, [deleteCheckInMutation, checkInListShortId, playSuccessSound, playErrorSound, haptic]);

    const handleCheckInAction = (attendee: Attendee, action: "check-in" | "check-in-and-mark-order-as-paid") => {
        checkInMutation.mutate({
            checkInListShortId: checkInListShortId,
            attendeePublicId: attendee.public_id,
            action: action,
        }, {
            onSuccess: (response) => {
                const {errors, data} = response;
                if (errors && errors[attendee.public_id]) {
                    showError(errors[attendee.public_id]);
                    playErrorSound();
                    haptic("error");
                    recordScan(attendee, attendee.public_id, "error");
                    return;
                }
                playSuccessSound();
                haptic("success");
                recordScan(attendee, attendee.public_id, "success");
                checkInModalHandlers.close();
                setSelectedAttendee(null);

                const createdCheckIn = data?.find((c: any) => c.attendee_id === attendee.id);
                const message = <Trans>{attendee.first_name} <b>checked in</b></Trans>;

                if (createdCheckIn) {
                    showSuccessWithUndo(
                        message,
                        () => undoCheckIn(attendee, String(createdCheckIn.short_id)),
                        {undoLabel: t`Undo`},
                    );
                } else {
                    showSuccess(message);
                }
            },
            onError: (error) => {
                playErrorSound();
                haptic("error");
                recordScan(attendee, attendee.public_id, "error");
                if (!networkStatus.online) {
                    showError(t`You are offline`);
                    return;
                }

                if (error instanceof AxiosError) {
                    showError(error?.response?.data?.message || t`Unable to check in attendee`);
                }
            },
        });
    };

    const handleCheckInToggle = (attendee: Attendee) => {
        if (attendee.check_in) {
            deleteCheckInMutation.mutate({
                checkInListShortId: checkInListShortId,
                checkInShortId: attendee.check_in.short_id,
            }, {
                onSuccess: () => {
                    showSuccess(<Trans>{attendee.first_name} <b>checked out</b> successfully</Trans>);
                    playSuccessSound();
                    haptic("tap");
                },
                onError: (error) => {
                    playErrorSound();
                    haptic("error");
                    if (!networkStatus.online) {
                        showError(t`You are offline`);
                        return;
                    }

                    if (error instanceof AxiosError) {
                        showError(error?.response?.data?.message || t`Unable to check out attendee`);
                    } else {
                        showError(t`Unable to check out attendee`);
                    }
                },
            });
            return;
        }

        const isAttendeeAwaitingPayment = attendee.status === "AWAITING_PAYMENT";

        if (allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            setSelectedAttendee(attendee);
            checkInModalHandlers.open();
            return;
        }

        if (!allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            showError(t`You cannot check in attendees with unpaid orders. This setting can be changed in the event settings.`);
            return;
        }

        handleCheckInAction(attendee, "check-in");
    };

    const handleQrCheckIn = useCallback(async (attendeePublicId: string) => {
        if (isProcessingRef.current) {
            return;
        }

        const now = Date.now();
        if (processedBarcodesRef.current.has(attendeePublicId) &&
            now - lastScanTimeRef.current < 3000) {
            showError(t`This ticket was just scanned. Please wait before scanning again.`);
            playErrorSound();
            return;
        }

        isProcessingRef.current = true;
        lastScanTimeRef.current = now;

        let attendee = attendees?.find(a => a.public_id === attendeePublicId);

        if (!attendee) {
            try {
                const {data} = await publicCheckInClient.getCheckInListAttendee(checkInListShortId, attendeePublicId);
                attendee = data;
            } catch (error) {
                showError(t`Unable to fetch attendee`);
                playErrorSound();
                recordScan(null, attendeePublicId, "error");
                isProcessingRef.current = false;
                return;
            }

            if (!attendee) {
                showError(t`Attendee not found`);
                playErrorSound();
                recordScan(null, attendeePublicId, "error");
                isProcessingRef.current = false;
                return;
            }
        }

        if (attendee.check_in) {
            showError(<Trans>{attendee.first_name} {attendee.last_name} is already checked in</Trans>);
            playErrorSound();
            haptic("warning");
            recordScan(attendee, attendeePublicId, "duplicate");
            processedBarcodesRef.current.add(attendeePublicId);
            isProcessingRef.current = false;
            return;
        }

        const isAttendeeAwaitingPayment = attendee.status === "AWAITING_PAYMENT";

        if (allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            setSelectedAttendee(attendee);
            checkInModalHandlers.open();
            isProcessingRef.current = false;
            return;
        }

        if (!allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            showError(t`You cannot check in attendees with unpaid orders. This setting can be changed in the event settings.`);
            playErrorSound();
            recordScan(attendee, attendeePublicId, "error");
            isProcessingRef.current = false;
            return;
        }

        processedBarcodesRef.current.add(attendeePublicId);
        setTimeout(() => {
            processedBarcodesRef.current.delete(attendeePublicId);
        }, 10000);

        await handleCheckInAction(attendee, "check-in");
        isProcessingRef.current = false;
    }, [attendees, checkInListShortId, allowOrdersAwaitingOfflinePaymentToCheckIn, checkInModalHandlers, handleCheckInAction, playErrorSound, recordScan]);

    const processBarcode = useCallback((barcode: string) => {
        if (barcode.startsWith("A-") && barcode.length > 3) {
            handleQrCheckIn(barcode);
        }
    }, [handleQrCheckIn]);

    useEffect(() => {
        const handleFocus = () => setPageHasFocus(true);
        const handleBlur = () => setPageHasFocus(false);

        window.addEventListener("focus", handleFocus);
        window.addEventListener("blur", handleBlur);

        return () => {
            window.removeEventListener("focus", handleFocus);
            window.removeEventListener("blur", handleBlur);
        };
    }, []);

    // HID scanner listener — only active on Scan tab in USB mode. Ignores key
    // events while focus is in an input so manual search still works.
    useEffect(() => {
        const usbListeningActive = activeTab === "scan" && scanMode === "usb";
        if (!usbListeningActive) {
            setCurrentBarcode("");
            return;
        }

        const handleKeyPress = (e: KeyboardEvent) => {
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
                return;
            }

            if (e.key === "Enter") {
                if (currentBarcode.length > 0) {
                    processBarcode(currentBarcode);
                    setCurrentBarcode("");
                }
            } else if (e.key.length === 1) {
                setCurrentBarcode(prev => {
                    const newBarcode = prev + e.key;

                    if (barcodeTimeoutRef.current) {
                        clearTimeout(barcodeTimeoutRef.current);
                    }

                    barcodeTimeoutRef.current = setTimeout(() => {
                        if (newBarcode.startsWith("A-") && newBarcode.length > 3) {
                            processBarcode(newBarcode);
                        }
                        setCurrentBarcode("");
                    }, 100);

                    return newBarcode;
                });
            }
        };

        window.addEventListener("keypress", handleKeyPress);

        return () => {
            window.removeEventListener("keypress", handleKeyPress);
            if (barcodeTimeoutRef.current) {
                clearTimeout(barcodeTimeoutRef.current);
            }
        };
    }, [activeTab, scanMode, currentBarcode, processBarcode]);

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

    // Scoped lists become unusable when their occurrence is cancelled — the
    // occurrence no longer exists for attendees to be checked in against.
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

    // Filtered stats drive the progress chip when an occurrence is selected;
    // otherwise the list's own totals (pre-computed server-side) are used.
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
                    {/* Subtitle for scoped lists so staff know which session they're on. */}
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

            {/* Persistent across tabs. Hidden for scoped lists (header shows it) and single events. */}
            {showOccurrenceFilter && event?.timezone && (
                <div className={classes.occurrenceFilterBar}>
                    <OccurrenceFilterPill
                        occurrences={pillOccurrences ?? []}
                        activeOccurrenceId={occurrenceFilter}
                        timezone={event.timezone}
                        onSelect={setOccurrenceFilter}
                    />
                </div>
            )}

            <main className={classes.content}>
                {activeTab === "scan" && (
                    <ScanTab
                        mode={scanMode}
                        onModeChange={setScanMode}
                        hidPageHasFocus={pageHasFocus}
                        hidBuffer={currentBarcode}
                        isSoundOn={isSoundOn}
                        onSoundToggle={() => setIsSoundOn(!isSoundOn)}
                        onAttendeeScanned={handleQrCheckIn}
                        onOpenRecentScan={setDetailAttendeePublicId}
                        recentScans={recentScans}
                    />
                )}
                {activeTab === "search" && (
                    <>
                        <SearchTab
                            attendees={attendees}
                            products={products}
                            searchQuery={searchQuery}
                            onSearchChange={setSearchQuery}
                            onCheckInToggle={handleCheckInToggle}
                            onOpenDetail={setDetailAttendeePublicId}
                            isLoading={attendeesQuery.isFetching}
                            isCheckInPending={checkInMutation.isPending}
                            isDeletePending={deleteCheckInMutation.isPending}
                            allowOrdersAwaitingOfflinePaymentToCheckIn={allowOrdersAwaitingOfflinePaymentToCheckIn || false}
                            eventType={event?.type as EventType | undefined}
                            timezone={event?.timezone}
                            showRowOccurrences={showOccurrenceFilter}
                        />
                    </>
                )}
                {activeTab === "stats" && (
                    <StatsTab
                        checkInListShortId={checkInListShortId}
                        enabled={!!checkInList?.is_active && !checkInList?.is_expired}
                        eventOccurrenceId={showOccurrenceFilter ? occurrenceFilter : null}
                    />
                )}
            </main>

            <BottomNav active={activeTab} onChange={setActiveTab}/>

            <CheckInOptionsModal
                isOpen={checkInModalOpen}
                attendee={selectedAttendee}
                isPending={checkInMutation.isPending}
                onClose={() => {
                    checkInModalHandlers.close();
                    setSelectedAttendee(null);
                }}
                onCheckIn={(action) => selectedAttendee && handleCheckInAction(selectedAttendee, action)}
            />
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
            <AttendeeDetailSheet
                checkInListShortId={checkInListShortId}
                attendeePublicId={detailAttendeePublicId}
                eventType={event?.type}
                timezone={event?.timezone}
                onClose={() => setDetailAttendeePublicId(null)}
                isActionPending={checkInMutation.isPending || deleteCheckInMutation.isPending}
                onCheckInToggle={(detail) => {
                    const attendee: Attendee = {
                        id: detail.id,
                        product_id: detail.product_id,
                        product_price_id: 0,
                        order_id: detail.order?.id ?? 0,
                        status: detail.status,
                        first_name: detail.first_name,
                        last_name: detail.last_name,
                        email: detail.email,
                        public_id: detail.public_id,
                        short_id: detail.public_id,
                        check_in: detail.check_ins?.[0] ? {
                            id: detail.check_ins[0].id,
                            attendee_id: detail.check_ins[0].attendee_id,
                            check_in_list_id: detail.check_ins[0].check_in_list_id,
                            product_id: detail.product_id,
                            event_id: 0,
                            short_id: detail.check_ins[0].short_id,
                            order_id: detail.check_ins[0].order_id,
                            created_at: detail.check_ins[0].checked_in_at,
                        } : undefined,
                    };
                    handleCheckInToggle(attendee);
                }}
            />
            <audio ref={scanSuccessAudioRef} src="/sounds/scan-success.wav"/>
            <audio ref={scanErrorAudioRef} src="/sounds/scan-error.wav"/>
        </div>
    );
};

export default CheckIn;
