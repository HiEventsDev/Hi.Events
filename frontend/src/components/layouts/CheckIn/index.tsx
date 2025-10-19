import {useParams} from "react-router";
import {useGetCheckInListPublic} from "../../../queries/useGetCheckInListPublic.ts";
import {useCallback, useEffect, useRef, useState} from "react";
import {useDebouncedValue, useDisclosure, useNetwork} from "@mantine/hooks";
import {Attendee, QueryFilters, QueryFilterOperator} from "../../../types.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {AxiosError} from "axios";
import classes from "./CheckIn.module.scss";
import {ActionIcon, Modal} from "@mantine/core";
import {SearchBar} from "../../common/SearchBar";
import {IconInfoCircle, IconQrcode, IconVolume, IconVolumeOff} from "@tabler/icons-react";
import {QRScannerComponent} from "../../common/AttendeeCheckInTable/QrScanner.tsx";
import {useGetCheckInListAttendees} from "../../../queries/useGetCheckInListAttendeesPublic.ts";
import {useCreateCheckInPublic} from "../../../mutations/useCreateCheckInPublic.ts";
import {useDeleteCheckInPublic} from "../../../mutations/useDeleteCheckInPublic.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {Countdown} from "../../common/Countdown";
import Truncate from "../../common/Truncate";
import {Header} from "../../common/Header";
import {publicCheckInClient} from "../../../api/check-in.client.ts";
import {isSsr} from "../../../utilites/helpers.ts";
import {AttendeeList} from "../../common/CheckIn/AttendeeList";
import {CheckInOptionsModal} from "../../common/CheckIn/CheckInOptionsModal";
import {ScannerSelectionModal} from "../../common/CheckIn/ScannerSelectionModal";
import {CheckInInfoModal} from "../../common/CheckIn/CheckInInfoModal";
import {HidScannerStatus} from "../../common/CheckIn/HidScannerStatus";
import {Button} from "@mantine/core";

const CheckIn = () => {
    const networkStatus = useNetwork();
    const {checkInListShortId} = useParams();
    const CheckInListQuery = useGetCheckInListPublic(checkInListShortId);
    const checkInList = CheckInListQuery?.data?.data;
    const event = checkInList?.event;
    const eventSettings = event?.settings;
    const [searchQuery, setSearchQuery] = useState('');
    const [searchQueryDebounced] = useDebouncedValue(searchQuery, 200);
    const [qrScannerOpen, setQrScannerOpen] = useState(false);
    const [scannerSelectionOpen, setScannerSelectionOpen] = useState(false);
    const [hidScannerMode, setHidScannerMode] = useState(false);
    const [currentBarcode, setCurrentBarcode] = useState('');
    const [pageHasFocus, setPageHasFocus] = useState(true);
    const barcodeTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const isProcessingRef = useRef(false);
    const processedBarcodesRef = useRef<Set<string>>(new Set());
    const lastScanTimeRef = useRef<number>(0);
    const scanSuccessAudioRef = useRef<HTMLAudioElement | null>(null);
    const scanErrorAudioRef = useRef<HTMLAudioElement | null>(null);
    const [isSoundOn, setIsSoundOn] = useState(() => {
        if (isSsr()) return true;
        // Use a unified sound setting for all scanners
        const storedIsSoundOn = localStorage.getItem("scannerSoundOn");
        return storedIsSoundOn === null ? true : JSON.parse(storedIsSoundOn);
    });
    const [selectedAttendee, setSelectedAttendee] = useState<Attendee | null>(null);
    const [checkInModalOpen, checkInModalHandlers] = useDisclosure(false);
    const [infoModalOpen, infoModalHandlers] = useDisclosure(false, {
            onOpen: () => {
                CheckInListQuery.refetch();
            }
        }
    );

    const products = checkInList?.products;
    const queryFilters: QueryFilters = {
        pageNumber: 1,
        query: searchQueryDebounced,
        perPage: 150,
        filterFields: {
            status: {operator: QueryFilterOperator.Equals, value: 'ACTIVE'},
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
    const areOfflinePaymentsEnabled = eventSettings?.payment_providers?.includes('OFFLINE');
    const allowOrdersAwaitingOfflinePaymentToCheckIn = areOfflinePaymentsEnabled
        && eventSettings?.allow_orders_awaiting_offline_payment_to_check_in;

    // Save sound preference to localStorage
    useEffect(() => {
        if (!isSsr()) {
            localStorage.setItem("scannerSoundOn", JSON.stringify(isSoundOn));
        }
    }, [isSoundOn]);

    // Sound helpers
    const playSuccessSound = useCallback(() => {
        if (isSoundOn && scanSuccessAudioRef.current) {
            scanSuccessAudioRef.current.play().catch(() => {
                // Ignore audio play errors (e.g., user hasn't interacted with page)
            });
        }
    }, [isSoundOn]);

    const playErrorSound = useCallback(() => {
        if (isSoundOn && scanErrorAudioRef.current) {
            scanErrorAudioRef.current.play().catch(() => {
                // Ignore audio play errors (e.g., user hasn't interacted with page)
            });
        }
    }, [isSoundOn]);

    const playClickSound = useCallback(() => {
        if (isSoundOn && scanSuccessAudioRef.current) {
            // Use success sound for click feedback
            scanSuccessAudioRef.current.currentTime = 0; // Reset to start for quick successive clicks
            scanSuccessAudioRef.current.play().catch(() => {
                // Ignore audio play errors
            });
        }
    }, [isSoundOn]);

    const handleCheckInAction = (attendee: Attendee, action: 'check-in' | 'check-in-and-mark-order-as-paid') => {
        checkInMutation.mutate({
            checkInListShortId: checkInListShortId,
            attendeePublicId: attendee.public_id,
            action: action,
        }, {
            onSuccess: ({errors}) => {
                if (errors && errors[attendee.public_id]) {
                    showError(errors[attendee.public_id]);
                    playErrorSound();
                    return;
                }
                showSuccess(<Trans>{attendee.first_name} <b>checked in</b> successfully</Trans>);
                playSuccessSound();
                checkInModalHandlers.close();
                setSelectedAttendee(null);
            },
            onError: (error) => {
                playErrorSound();
                if (!networkStatus.online) {
                    showError(t`You are offline`);
                    return;
                }

                if (error instanceof AxiosError) {
                    showError(error?.response?.data?.message || t`Unable to check in attendee`);
                }
            }
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
                },
                onError: (error) => {
                    playErrorSound();
                    if (!networkStatus.online) {
                        showError(t`You are offline`);
                        return;
                    }

                    if (error instanceof AxiosError) {
                        showError(error?.response?.data?.message || t`Unable to check out attendee`);
                    } else {
                        showError(t`Unable to check out attendee`);
                    }
                }
            });
            return;
        }

        const isAttendeeAwaitingPayment = attendee.status === 'AWAITING_PAYMENT';

        if (allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            setSelectedAttendee(attendee);
            checkInModalHandlers.open();
            return;
        }

        if (!allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            showError(t`You cannot check in attendees with unpaid orders. This setting can be changed in the event settings.`);
            return;
        }

        handleCheckInAction(attendee, 'check-in');
    };

    const handleQrCheckIn = useCallback(async (attendeePublicId: string) => {
        // Prevent processing if already handling a request
        if (isProcessingRef.current) {
            return;
        }

        // Check if this barcode was recently processed (within last 3 seconds)
        const now = Date.now();
        if (processedBarcodesRef.current.has(attendeePublicId) &&
            now - lastScanTimeRef.current < 3000) {
            showError(t`This ticket was just scanned. Please wait before scanning again.`);
            playErrorSound();
            return;
        }

        isProcessingRef.current = true;
        lastScanTimeRef.current = now;

        // Find the attendee in the current list or fetch them
        let attendee = attendees?.find(a => a.public_id === attendeePublicId);

        if (!attendee) {
            try {
                const {data} = await publicCheckInClient.getCheckInListAttendee(checkInListShortId, attendeePublicId);
                attendee = data;
            } catch (error) {
                showError(t`Unable to fetch attendee`);
                playErrorSound();
                isProcessingRef.current = false;
                return;
            }

            if (!attendee) {
                showError(t`Attendee not found`);
                playErrorSound();
                isProcessingRef.current = false;
                return;
            }
        }

        // Check if already checked in
        if (attendee.check_in) {
            showError(<Trans>{attendee.first_name} {attendee.last_name} is already checked in</Trans>);
            playErrorSound();
            processedBarcodesRef.current.add(attendeePublicId);
            isProcessingRef.current = false;
            return;
        }

        const isAttendeeAwaitingPayment = attendee.status === 'AWAITING_PAYMENT';

        if (allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            setSelectedAttendee(attendee);
            checkInModalHandlers.open();
            isProcessingRef.current = false;
            return;
        }

        if (!allowOrdersAwaitingOfflinePaymentToCheckIn && isAttendeeAwaitingPayment) {
            showError(t`You cannot check in attendees with unpaid orders. This setting can be changed in the event settings.`);
            playErrorSound();
            isProcessingRef.current = false;
            return;
        }

        // Add to processed set before making the request
        processedBarcodesRef.current.add(attendeePublicId);

        // Clear old entries from the set after 10 seconds
        setTimeout(() => {
            processedBarcodesRef.current.delete(attendeePublicId);
        }, 10000);

        await handleCheckInAction(attendee, 'check-in');
        isProcessingRef.current = false;
    }, [attendees, checkInListShortId, allowOrdersAwaitingOfflinePaymentToCheckIn, checkInModalHandlers, handleCheckInAction, playErrorSound]);


    // Process completed barcode
    const processBarcode = useCallback((barcode: string) => {
        if (barcode.startsWith('A-') && barcode.length > 3) {
            handleQrCheckIn(barcode);
        }
    }, [handleQrCheckIn]);

    // Track page focus
    useEffect(() => {
        const handleFocus = () => setPageHasFocus(true);
        const handleBlur = () => setPageHasFocus(false);

        window.addEventListener('focus', handleFocus);
        window.addEventListener('blur', handleBlur);

        return () => {
            window.removeEventListener('focus', handleFocus);
            window.removeEventListener('blur', handleBlur);
        };
    }, []);

    // Global keyboard listener for HID scanner mode
    useEffect(() => {
        if (!hidScannerMode) return;

        const handleKeyPress = (e: KeyboardEvent) => {
            // Ignore if user is typing in an input field
            if (e.target instanceof HTMLInputElement ||
                e.target instanceof HTMLTextAreaElement) {
                return;
            }

            if (e.key === 'Enter') {
                // Process the accumulated barcode on Enter
                if (currentBarcode.length > 0) {
                    processBarcode(currentBarcode);
                    setCurrentBarcode('');
                }
            } else if (e.key.length === 1) {
                // Accumulate characters
                setCurrentBarcode(prev => {
                    const newBarcode = prev + e.key;

                    // Clear any existing timeout
                    if (barcodeTimeoutRef.current) {
                        clearTimeout(barcodeTimeoutRef.current);
                    }

                    // Set timeout to clear barcode if no more input (scanner stopped)
                    barcodeTimeoutRef.current = setTimeout(() => {
                        // Auto-process if it looks like a complete barcode
                        if (newBarcode.startsWith('A-') && newBarcode.length > 3) {
                            processBarcode(newBarcode);
                        }
                        setCurrentBarcode('');
                    }, 100);

                    return newBarcode;
                });
            }
        };

        window.addEventListener('keypress', handleKeyPress);

        return () => {
            window.removeEventListener('keypress', handleKeyPress);
            if (barcodeTimeoutRef.current) {
                clearTimeout(barcodeTimeoutRef.current);
            }
        };
    }, [hidScannerMode, currentBarcode, processBarcode]);

    if (CheckInListQuery.error && (CheckInListQuery.error as any).response?.status === 404) {
        return (
            <NoResultsSplash
                heading={t`Check-in list not found`}
                imageHref={'/blank-slate/check-in-lists.svg'}
                subHeading={(
                    <>
                        <p>
                            {t`The check-in list you are looking for does not exist.`}
                        </p>
                    </>
                )}
            />)
    }

    if (checkInList?.is_expired) {
        return (
            <NoResultsSplash
                heading={t`Check-in list has expired`}
                imageHref={'/blank-slate/check-in-lists.svg'}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                This check-in list has expired and is no longer available for check-ins.
                            </Trans>
                        </p>
                    </>
                )}
            />)
    }

    if (checkInList && !checkInList?.is_active) {
        return (
            <NoResultsSplash
                heading={t`Check-in list is not active`}
                imageHref={'/blank-slate/check-in-lists.svg'}
                subHeading={(
                    <>
                        <p>
                            {t`This check-in list is not yet active and is not available for check-ins.`}
                        </p>
                        <p>
                            Check-in list will activate in{' '}<br/>
                            <b>
                                <Countdown
                                    targetDate={checkInList.activates_at as string}
                                    onExpiry={() => CheckInListQuery.refetch()}
                                />
                            </b>
                        </p>
                    </>
                )}
            />)
    }

    return (
        <div className={classes.container}>
            <Header
                fullWidth
                rightContent={(
                    <>
                        {!networkStatus.online && (
                            <div className={classes.offline}/>
                        )}
                        <ActionIcon
                            display={'flex'}
                            variant={'transparent'}
                            color={'white'}
                            onClick={() => infoModalHandlers.open()}
                        >
                            <IconInfoCircle/>
                        </ActionIcon>
                    </>
                )}/>
            <HidScannerStatus
                isActive={hidScannerMode}
                pageHasFocus={pageHasFocus}
                onDisable={() => setHidScannerMode(false)}
            />
            <div className={classes.header}>
                <div>
                    <h4 className={classes.title}>
                        <Truncate text={checkInList?.name} length={30}/>
                    </h4>
                </div>
                <div className={classes.search}>
                    <div className={classes.searchBar}>
                        <SearchBar
                            className={classes.searchInput}
                            mb={20}
                            value={searchQuery}
                            onChange={(event) => setSearchQuery(event.target.value)}
                            onClear={() => setSearchQuery('')}
                            placeholder={t`Search by name, order #, attendee # or email...`}
                        />
                        <Button variant={'light'} size={'md'} className={classes.scanButton}
                                onClick={() => setScannerSelectionOpen(true)} leftSection={<IconQrcode/>}>
                            {t`Scan`}
                        </Button>
                        <ActionIcon 
                            aria-label={isSoundOn ? t`Turn sound off` : t`Turn sound on`} 
                            variant={'light'} 
                            size={'xl'}
                            onClick={() => setIsSoundOn(!isSoundOn)}
                        >
                            {isSoundOn ? <IconVolume size={24}/> : <IconVolumeOff size={24}/>}
                        </ActionIcon>
                        <ActionIcon aria-label={t`Scan`} variant={'light'} size={'xl'}
                                    className={classes.scanIcon}
                                    onClick={() => setScannerSelectionOpen(true)}>
                            <IconQrcode size={32}/>
                        </ActionIcon>
                    </div>
                </div>
            </div>
            <AttendeeList
                attendees={attendees}
                products={products}
                isLoading={attendeesQuery.isFetching}
                isCheckInPending={checkInMutation.isPending}
                isDeletePending={deleteCheckInMutation.isPending}
                allowOrdersAwaitingOfflinePaymentToCheckIn={allowOrdersAwaitingOfflinePaymentToCheckIn || false}
                onCheckInToggle={handleCheckInToggle}
                onClickSound={playClickSound}
            />
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
            <ScannerSelectionModal
                isOpen={scannerSelectionOpen}
                isHidScannerActive={hidScannerMode}
                onClose={() => setScannerSelectionOpen(false)}
                onCameraSelect={() => {
                    setScannerSelectionOpen(false);
                    setQrScannerOpen(true);
                }}
                onHidScannerSelect={() => {
                    setScannerSelectionOpen(false);
                    if (!hidScannerMode) {
                        setHidScannerMode(true);
                    }
                }}
            />
            {qrScannerOpen && (
                <Modal.Root
                    opened
                    onClose={() => setQrScannerOpen(false)}
                    fullScreen
                    radius={0}
                    transitionProps={{transition: 'fade', duration: 200}}
                    padding={'none'}
                >
                    <Modal.Overlay/>
                    <Modal.Content>
                        <QRScannerComponent
                            onAttendeeScanned={handleQrCheckIn}
                            onClose={() => setQrScannerOpen(false)}
                            isSoundOn={isSoundOn}
                        />
                    </Modal.Content>
                </Modal.Root>
            )}
            <CheckInInfoModal
                isOpen={infoModalOpen}
                checkInList={checkInList}
                onClose={infoModalHandlers.close}
            />
            {/* Audio elements for HID scanner sounds */}
            <audio ref={scanSuccessAudioRef} src="/sounds/scan-success.wav"/>
            <audio ref={scanErrorAudioRef} src="/sounds/scan-error.wav"/>
        </div>
    );
}

export default CheckIn;
