import {useCallback, useEffect, useRef, useState} from "react";
import {useDebouncedValue, useDisclosure, useNetwork} from "@mantine/hooks";
import {AxiosError} from "axios";
import {t, Trans} from "@lingui/macro";
import {Attendee, CheckInList, EventType, QueryFilterOperator, QueryFilters} from "../types.ts";
import {showError, showInfo, showSuccess, showSuccessWithUndo} from "../utilites/notifications.tsx";
import {isSsr} from "../utilites/helpers.ts";
import {useHaptics} from "./useHaptics.ts";
import {useCheckInOccurrenceFilter} from "./useCheckInOccurrenceFilter.ts";
import {useGetCheckInListAttendees} from "../queries/useGetCheckInListAttendeesPublic.ts";
import {useCreateCheckInPublic} from "../mutations/useCreateCheckInPublic.ts";
import {useDeleteCheckInPublic} from "../mutations/useDeleteCheckInPublic.ts";
import {publicCheckInClient} from "../api/check-in.client.ts";
import {ScanMode} from "../components/layouts/CheckIn/tabs/ScanTab.tsx";
import {RecentScan, RecentScanStatus} from "../components/layouts/CheckIn/types.ts";

const MAX_RECENT_SCANS = 20;

interface UseCheckInControllerOptions {
    checkInListShortId: string | undefined;
    checkInList: CheckInList | undefined;
    hidListeningEnabled: boolean;
    occurrenceOverride?: number | null;
}

export const useCheckInController = ({
                                         checkInListShortId,
                                         checkInList,
                                         hidListeningEnabled,
                                         occurrenceOverride,
                                     }: UseCheckInControllerOptions) => {
    const networkStatus = useNetwork();
    const event = checkInList?.event;
    const eventSettings = event?.settings;

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

    const products = checkInList?.products;
    const pillOccurrences = checkInList?.event_occurrences ?? event?.occurrences;
    const isOccurrencePinned = occurrenceOverride !== undefined;

    const showOccurrenceFilter =
        !isOccurrencePinned
        && event?.type === EventType.RECURRING
        && !checkInList?.event_occurrence_id
        && (pillOccurrences?.length ?? 0) > 0;
    const {occurrenceId: occurrenceFilter, setOccurrenceId: setOccurrenceFilter, didClearStale} =
        useCheckInOccurrenceFilter(checkInListShortId, pillOccurrences, !isOccurrencePinned);

    useEffect(() => {
        if (didClearStale) {
            showInfo(t`Your saved date filter is no longer available — showing all dates.`);
        }
    }, [didClearStale]);

    const activeOccurrenceId = isOccurrencePinned
        ? occurrenceOverride
        : (showOccurrenceFilter ? occurrenceFilter : null);

    const queryFilters: QueryFilters = {
        pageNumber: 1,
        query: searchQueryDebounced,
        perPage: 150,
        filterFields: {
            status: {operator: QueryFilterOperator.Equals, value: "ACTIVE"},
            ...(activeOccurrenceId !== null
                ? {event_occurrence_id: {operator: QueryFilterOperator.Equals, value: String(activeOccurrenceId)}}
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
    const allowOrdersAwaitingOfflinePaymentToCheckIn = !!(areOfflinePaymentsEnabled
        && eventSettings?.allow_orders_awaiting_offline_payment_to_check_in);

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
        pushRecentScan({name, code, status, seatLabel: attendee?.seat_label ?? null});
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
                const seatLabel = attendee.seat_label;
                const message = seatLabel
                    ? <Trans>{attendee.first_name} <b>checked in</b> · {seatLabel}</Trans>
                    : <Trans>{attendee.first_name} <b>checked in</b></Trans>;

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
            const seatLabel = attendee.seat_label;
            showError(seatLabel
                ? <Trans>{attendee.first_name} {attendee.last_name} is already checked in · {seatLabel}</Trans>
                : <Trans>{attendee.first_name} {attendee.last_name} is already checked in</Trans>);
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

    useEffect(() => {
        const usbListeningActive = hidListeningEnabled && scanMode === "usb";
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
    }, [hidListeningEnabled, scanMode, currentBarcode, processBarcode]);

    const closeCheckInModal = () => {
        checkInModalHandlers.close();
        setSelectedAttendee(null);
    };

    return {
        scanMode,
        setScanMode,
        isSoundOn,
        toggleSound: () => setIsSoundOn(!isSoundOn),
        pageHasFocus,
        hidBuffer: currentBarcode,
        recentScans,
        searchQuery,
        setSearchQuery,
        attendees,
        isAttendeesLoading: attendeesQuery.isFetching,
        products,
        pillOccurrences,
        showOccurrenceFilter,
        occurrenceFilter,
        setOccurrenceFilter,
        activeOccurrenceId,
        handleQrCheckIn,
        handleCheckInToggle,
        handleCheckInAction,
        detailAttendeePublicId,
        setDetailAttendeePublicId,
        selectedAttendee,
        checkInModalOpen,
        closeCheckInModal,
        isCheckInPending: checkInMutation.isPending,
        isDeletePending: deleteCheckInMutation.isPending,
        allowOrdersAwaitingOfflinePaymentToCheckIn,
        scanSuccessAudioRef,
        scanErrorAudioRef,
    };
};

export type CheckInController = ReturnType<typeof useCheckInController>;
