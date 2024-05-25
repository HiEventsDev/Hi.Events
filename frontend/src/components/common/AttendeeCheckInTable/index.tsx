import {ActionIcon, Button, Loader, Modal, Switch} from "@mantine/core";
import classes from './AttendeesCheckInTable.module.scss';
import {useParams} from "react-router-dom";
import {Card} from "../Card";
import {PageTitle} from "../PageTitle";
import {SearchBar} from "../SearchBar";
import {useState} from "react";
import {useDebouncedValue} from "@mantine/hooks";
import {useGetAttendees} from "../../../queries/useGetAttendees.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useCheckInAttendee} from "../../../mutations/useCheckInAttendee.ts";
import {Attendee, QueryFilters} from "../../../types.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {AxiosError} from "axios";
import {IconQrcode} from "@tabler/icons-react";
import {QRScannerComponent} from "./QrScanner.tsx";
import {t, Trans} from "@lingui/macro";
import {useGetEventCheckInStats} from "../../../queries/useGetEventCheckInStats.ts";

export const AttendeesCheckInTable = () => {
    const {eventId} = useParams();
    const [searchQuery, setSearchQuery] = useState('');
    const [searchQueryDebounced] = useDebouncedValue(searchQuery, 200);
    const [qrScannerOpen, setQrScannerOpen] = useState(false);
    const {data: {tickets} = {}} = useGetEvent(eventId);
    const queryFilters: QueryFilters = {
        pageNumber: 1,
        query: searchQueryDebounced,
        perPage: 100,
        filterFields: {
            status: ['ACTIVE'],
        },
    };
    const attendeesQuery = useGetAttendees(eventId, queryFilters);
    const attendees = attendeesQuery?.data?.data;
    const mutation = useCheckInAttendee();
    const {data: eventStats} = useGetEventCheckInStats(eventId);

    const handleCheckInToggle = (checked: boolean, attendee: Attendee) => {
        mutation.mutate({
            eventId: eventId,
            attendeePublicId: attendee.public_id,
            action: checked ? 'check_in' : 'check_out',
            pagination: queryFilters,
        }, {
            onSuccess: ({data: attendee}, variables) => {
                showSuccess(<Trans>Successfully
                    checked <b>{attendee.first_name} {attendee.last_name}</b> {variables.action === 'check_in' ? 'in' : 'out'}
                </Trans>);
            },
            onError: (error, variables) => {
                if (error instanceof AxiosError) {
                    showError(error?.response?.data.message ||
                        <Trans>Unable to {variables.action ? t`check in` : t`check out`} attendee</Trans>);
                }
            }
        })
    }

    const handleQrCheckIn = (attendeePublicId: string, onRequestComplete?: () => void) => {
        mutation.mutate({
            eventId: eventId,
            attendeePublicId: attendeePublicId,
            action: 'check_in',
            pagination: queryFilters,
        }, {
            onSuccess: ({data: attendee}, variables) => {
                if (onRequestComplete) {
                    onRequestComplete()
                }
                showSuccess(<Trans>Successfully
                    checked <b>{attendee.first_name} {attendee.last_name}</b> {variables.action === 'check_in' ? 'in' : 'out'}
                </Trans>);
            },
            onError: (error, variables) => {
                if (onRequestComplete) {
                    onRequestComplete()
                }
                if (error instanceof AxiosError) {
                    showError(error?.response?.data.message ||
                        <Trans>Unable to {variables.action ? t`check in` : t`check out`} attendee</Trans>);
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
                            <Card className={classes.attendee} key={attendee.public_id}>
                                <div className={classes.details}>
                                    <div>
                                        {attendee.first_name} {attendee.last_name}
                                    </div>
                                    <div>
                                        <b>{attendee.public_id}</b>
                                    </div>
                                    <div style={{color: 'gray'}}>
                                        {tickets.find(ticket => ticket.id === attendee.ticket_id)?.title}
                                    </div>
                                </div>
                                <div className={classes.actions}>
                                    <Switch
                                        color={'green'}
                                        onLabel={t`Checked In`}
                                        offLabel={t`Not Checked In`}
                                        size="xl"
                                        disabled={mutation.isLoading}
                                        checked={attendee.checked_in_at !== null}
                                        value={attendee.public_id}
                                        onChange={(event) => handleCheckInToggle(event.target.checked, attendee)}
                                    />
                                </div>
                            </Card>
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

    return (
        <>
            <Card className={classes.header}>
                <div className={classes.search}>
                    <PageTitle>
                        {t`Check In`}{'  '}
                        {eventStats && (
                            <span className={classes.checkInCount}>
                            <Trans>
                                {eventStats && `${eventStats.total_checked_in_attendees}/${eventStats.total_attendees}`} checked in
                            </Trans>
                        </span>
                        )}
                    </PageTitle>

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
            </Card>
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
        </>
    );
};