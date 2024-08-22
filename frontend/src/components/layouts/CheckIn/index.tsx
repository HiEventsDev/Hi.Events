import {useParams} from "react-router-dom";
import {useGetCheckInListPublic} from "../../../queries/useGetCheckInListPublic.ts";
import {useState} from "react";
import {useDebouncedValue, useDisclosure, useNetwork} from "@mantine/hooks";
import {Attendee, QueryFilters} from "../../../types.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {AxiosError} from "axios";
import classes from "./CheckIn.module.scss";
import {ActionIcon, Button, Loader, Modal, Progress} from "@mantine/core";
import {SearchBar} from "../../common/SearchBar";
import {IconInfoCircle, IconQrcode, IconTicket} from "@tabler/icons-react";
import {QRScannerComponent} from "../../common/AttendeeCheckInTable/QrScanner.tsx";
import {useGetCheckInListAttendees} from "../../../queries/useGetCheckInListAttendeesPublic.ts";
import {useCreateCheckInPublic} from "../../../mutations/useCreateCheckInPublic.ts";
import {useDeleteCheckInPublic} from "../../../mutations/useDeleteCheckInPublic.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {Countdown} from "../../common/Countdown";
import Truncate from "../../common/Truncate";
import {Header} from "../../common/Header";

const CheckIn = () => {
    const networkStatus = useNetwork();
    const {checkInListShortId} = useParams();
    const CheckInListQuery = useGetCheckInListPublic(checkInListShortId);
    const checkInList = CheckInListQuery?.data?.data;
    const [searchQuery, setSearchQuery] = useState('');
    const [searchQueryDebounced] = useDebouncedValue(searchQuery, 200);
    const [qrScannerOpen, setQrScannerOpen] = useState(false);
    const [infoModalOpen, infoModalHandlers] = useDisclosure(false, {
            onOpen: () => {
                CheckInListQuery.refetch();
            }
        }
    );
    const tickets = checkInList?.tickets;
    const queryFilters: QueryFilters = {
        pageNumber: 1,
        query: searchQueryDebounced,
        perPage: 100,
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
            })
            return;
        }

        checkInMutation.mutate({
                checkInListShortId: checkInListShortId,
                attendeePublicId: attendee.public_id,
            }, {
                onSuccess: ({errors}) => {
                    // Show error if there is an error for this specific attendee
                    // It's a bulk endpoint, so even if there's an error it returns a 200
                    if (errors && errors[attendee.public_id]) {
                        showError(errors[attendee.public_id]);
                        return;
                    }

                    showSuccess(<Trans>{attendee.first_name} <b>checked in</b> successfully</Trans>);
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
            }
        )
    }

    const handleQrCheckIn = (attendeePublicId: string, onRequestComplete?: () => void) => {
        checkInMutation.mutate({
            checkInListShortId: checkInListShortId,
            attendeePublicId: attendeePublicId,
        }, {
            onSuccess: ({errors}) => {
                if (onRequestComplete) {
                    onRequestComplete()
                }
                // Show error if there is an error for this specific attendee
                // It's a bulk endpoint, so even if there's an error it returns a 200
                if (errors && errors[attendeePublicId]) {
                    showError(errors[attendeePublicId]);
                    return;
                }

                showSuccess(t`Checked in successfully`);
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
        })
    }

    const Attendees = () => {
        const Container = () => {
            if (attendeesQuery.isFetching || !attendees || !tickets) {
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
                        return (
                            <div className={classes.attendee} key={attendee.public_id}>
                                <div className={classes.details}>
                                    <div>
                                        {attendee.first_name} {attendee.last_name}
                                    </div>
                                    <div>
                                        <b>{attendee.public_id}</b>
                                    </div>
                                    <div className={classes.ticket}>
                                       <IconTicket size={15}/> {tickets.find(ticket => ticket.id === attendee.ticket_id)?.title}
                                    </div>
                                </div>
                                <div className={classes.actions}>
                                    <Button
                                        onClick={() => handleCheckInToggle(attendee)}
                                        disabled={checkInMutation.isLoading || deleteCheckInMutation.isLoading}
                                        loading={checkInMutation.isLoading || deleteCheckInMutation.isLoading}
                                        color={attendee.check_in ? 'red' : 'teal'}
                                    >
                                        {attendee.check_in ? t`Check Out` : t`Check In`}
                                    </Button>
                                    {/*{attendee.check_in && (*/}
                                    {/*    <div style={{color: 'gray', fontSize: 12, marginTop: 10}}>*/}
                                    {/*        checked in {relativeDate(attendee.check_in.checked_in_at)}*/}
                                    {/*    </div>*/}
                                    {/*)}*/}
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
                            <div className={classes.offline}>
                                {/*<IconNetworkOff color={'red'}/>*/}
                            </div>
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
                            placeholder={t`Seach by name, order #, attendee # or email...`}
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
                            onCheckIn={handleQrCheckIn}
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
                                <Truncate text={checkInList?.name} length={30}/> </Modal.Title>
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

export default CheckIn
