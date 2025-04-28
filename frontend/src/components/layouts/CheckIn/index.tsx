import {useParams} from "react-router";
import {useGetCheckInListPublic} from "../../../queries/useGetCheckInListPublic.ts";
import {useState} from "react";
import {useDebouncedValue, useDisclosure, useNetwork} from "@mantine/hooks";
import {Attendee, QueryFilters} from "../../../types.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {AxiosError} from "axios";
import classes from "./CheckIn.module.scss";
import {ActionIcon, Alert, Button, Loader, Modal, Progress, Stack} from "@mantine/core";
import {SearchBar} from "../../common/SearchBar";
import {
    IconAlertCircle,
    IconCreditCard,
    IconInfoCircle,
    IconQrcode,
    IconTicket,
    IconUserCheck
} from "@tabler/icons-react";
import {QRScannerComponent} from "../../common/AttendeeCheckInTable/QrScanner.tsx";
import {useGetCheckInListAttendees} from "../../../queries/useGetCheckInListAttendeesPublic.ts";
import {useCreateCheckInPublic} from "../../../mutations/useCreateCheckInPublic.ts";
import {useDeleteCheckInPublic} from "../../../mutations/useDeleteCheckInPublic.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {Countdown} from "../../common/Countdown";
import Truncate from "../../common/Truncate";
import {Header} from "../../common/Header";
import {publicCheckInClient} from "../../../api/check-in.client.ts";

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
            status: {operator: 'eq', value: 'ACTIVE'},
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

    const handleCheckInAction = (attendee: Attendee, action: 'check-in' | 'check-in-and-mark-order-as-paid') => {
        checkInMutation.mutate({
            checkInListShortId: checkInListShortId,
            attendeePublicId: attendee.public_id,
            action: action,
        }, {
            onSuccess: ({errors}) => {
                if (errors && errors[attendee.public_id]) {
                    showError(errors[attendee.public_id]);
                    return;
                }
                showSuccess(<Trans>{attendee.first_name} <b>checked in</b> successfully</Trans>);
                checkInModalHandlers.close();
                setSelectedAttendee(null);
            },
            onError: (error) => {
                if (!networkStatus.online) {
                    showError(t`You are offline`);
                    return;
                }

                if (error instanceof AxiosError) {
                    showError(error?.response?.data.message || t`Unable to check in attendee`);
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
                },
                onError: (error) => {
                    if (!networkStatus.online) {
                        showError(t`You are offline`);
                        return;
                    }

                    showError(error?.response?.data.message || t`Unable to check out attendee`);
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

    const handleQrCheckIn = async (attendeePublicId: string) => {
        // Find the attendee in the current list or fetch them
        let attendee = attendees?.find(a => a.public_id === attendeePublicId);

        if (!attendee) {
            try {
                const {data} = await publicCheckInClient.getCheckInListAttendee(checkInListShortId, attendeePublicId);
                attendee = data;
            } catch (error) {
                showError(t`Unable to fetch attendee`);
                return;
            }

            if (!attendee) {
                showError(t`Attendee not found`);
                return;
            }
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

    const checkInButtonText = (attendee: Attendee) => {
        if (!allowOrdersAwaitingOfflinePaymentToCheckIn && attendee.status === 'AWAITING_PAYMENT') {
            return t`Cannot Check In`;
        }

        if (attendee.check_in) {
            return t`Check Out`;
        }

        return t`Check In`;
    }

    const CheckInOptionsModal = () => {
        if (!selectedAttendee) return null;

        return (
            <Modal
                opened={checkInModalOpen}
                onClose={() => {
                    checkInModalHandlers.close();
                    setSelectedAttendee(null);
                }}
                title={<Trans>Check in {selectedAttendee.first_name} {selectedAttendee.last_name}</Trans>}
                size="md"
            >
                <Stack>
                    <Alert
                        icon={<IconAlertCircle size={20}/>}
                        variant={'light'}
                        title={t`Unpaid Order`}>
                        {t`This attendee has an unpaid order.`}
                    </Alert>
                    <Button
                        leftSection={<IconUserCheck size={20}/>}
                        onClick={() => handleCheckInAction(selectedAttendee, 'check-in')}
                        loading={checkInMutation.isPending}
                        fullWidth
                    >
                        {t`Check in only`}
                    </Button>
                    <Button
                        leftSection={<IconCreditCard size={20}/>}
                        onClick={() => handleCheckInAction(selectedAttendee, 'check-in-and-mark-order-as-paid')}
                        loading={checkInMutation.isPending}
                        variant="filled"
                        fullWidth
                    >
                        {t`Check in and mark order as paid`}
                    </Button>
                    <Button
                        onClick={checkInModalHandlers.close}
                        variant="light"
                        fullWidth
                    >
                        {t`Cancel`}
                    </Button>
                </Stack>
            </Modal>
        );
    };

    const Attendees = () => {
        const Container = () => {
            if (attendeesQuery.isFetching || !attendees || !products) {
                return (
                    <div className={classes.loading}>
                        <Loader size={40}/>
                    </div>
                )
            }

            if (attendees.length === 0) {
                return (
                    <div className={classes.noResults}>
                        No attendees to show.
                    </div>
                );
            }

            return (
                <div className={classes.attendees}>
                    {attendees.map(attendee => {
                        const isAttendeeAwaitingPayment = attendee.status === 'AWAITING_PAYMENT';

                        return (
                            <div className={classes.attendee} key={attendee.public_id}>
                                <div className={classes.details}>
                                    <div>
                                        {attendee.first_name} {attendee.last_name}
                                    </div>
                                    {isAttendeeAwaitingPayment && (
                                        <div className={classes.awaitingPayment}>
                                            {t`Awaiting payment`}
                                        </div>
                                    )}
                                    <div>
                                        <b>{attendee.public_id}</b>
                                    </div>
                                    <div className={classes.product}>
                                        <IconTicket
                                            size={15}/> {products.find(product => product.id === attendee.product_id)?.title}
                                    </div>
                                </div>
                                <div className={classes.actions}>
                                    {<Button
                                        onClick={() => handleCheckInToggle(attendee)}
                                        disabled={checkInMutation.isPending || deleteCheckInMutation.isPending}
                                        loading={checkInMutation.isPending || deleteCheckInMutation.isPending}
                                        color={(function () {
                                                if (attendee.check_in) {
                                                    return 'red';
                                                }
                                                if (isAttendeeAwaitingPayment && !allowOrdersAwaitingOfflinePaymentToCheckIn) {
                                                    return 'gray';
                                                }

                                                return 'teal';
                                            }
                                        )()}
                                    >
                                        {checkInButtonText(attendee)}
                                    </Button>}
                                </div>
                            </div>
                        )
                    })}
                </div>
            )
        }

        return (
            <div style={{position: 'relative'}}>
                <Container/>
            </div>
        );
    }

    if (CheckInListQuery.error && CheckInListQuery.error.response?.status === 404) {
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
                            onClick={() => infoModalHandlers.open()}
                        >
                            <IconInfoCircle/>
                        </ActionIcon>
                    </>
                )}/>
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
                                onClick={() => setQrScannerOpen(true)} leftSection={<IconQrcode/>}>
                            {t`Scan QR Code`}
                        </Button>
                        <ActionIcon aria-label={t`Scan QR Code`} variant={'light'} size={'xl'}
                                    className={classes.scanIcon}
                                    onClick={() => setQrScannerOpen(true)}>
                            <IconQrcode size={32}/>
                        </ActionIcon>
                    </div>
                </div>
            </div>
            <Attendees/>
            <CheckInOptionsModal/>
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
                        />
                    </Modal.Content>
                </Modal.Root>
            )}
            {infoModalOpen && (
                <Modal.Root
                    opened
                    radius={0}
                    onClose={infoModalHandlers.close}
                    transitionProps={{transition: 'fade', duration: 200}}
                    padding={'none'}
                >
                    <Modal.Overlay/>
                    <Modal.Content>
                        <Modal.Header>
                            <Modal.Title>
                                <Truncate text={checkInList?.name} length={30}/>
                            </Modal.Title>
                            <Modal.CloseButton/>
                        </Modal.Header>
                        <div className={classes.infoModal}>
                            <div className={classes.checkInCount}>
                                {checkInList && (
                                    <>
                                        <h4>
                                            <Trans>
                                                {`${checkInList.checked_in_attendees}/${checkInList.total_attendees}`} checked
                                                in
                                            </Trans>
                                        </h4>

                                        <Progress
                                            value={checkInList.checked_in_attendees / checkInList.total_attendees * 100}
                                            color={'teal'}
                                            size={'xl'}
                                            className={classes.progressBar}
                                        />

                                    </>
                                )}
                            </div>

                            {checkInList?.description && (
                                <div className={classes.description}>
                                    {checkInList.description}
                                </div>
                            )}
                        </div>
                    </Modal.Content>
                </Modal.Root>
            )}
        </div>
    );
}

export default CheckIn;
