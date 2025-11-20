import {Modal, Stack, Text, Group, Badge, Box, ScrollArea} from '@mantine/core';
import {IconCheck, IconX, IconQrcode} from '@tabler/icons-react';
import {Attendee, CheckInList, IdParam} from '../../../types';
import {t, Trans} from '@lingui/macro';
import {prettyDate} from '../../../utilites/dates';
import {useGetEventCheckInLists} from '../../../queries/useGetCheckInLists';
import {LoadingMask} from '../LoadingMask';
import classes from './CheckInStatusModal.module.scss';

interface CheckInStatusModalProps {
    attendee: Attendee;
    eventTimezone: string;
    eventId: IdParam;
    isOpen: boolean;
    onClose: () => void;
}

export const CheckInStatusModal = ({
    attendee,
    eventTimezone,
    eventId,
    isOpen,
    onClose
}: CheckInStatusModalProps) => {
    const {data: checkInListsResponse, isLoading} = useGetEventCheckInLists(
        eventId,
        {pageNumber: 1, perPage: 100}
    );

    if (isLoading) {
        return (
            <Modal.Root opened={isOpen} onClose={onClose} size="md">
                <Modal.Overlay/>
                <Modal.Content>
                    <Modal.Header>
                        <Modal.Title>
                            <Group gap="xs">
                                <IconQrcode size={20}/>
                                <Trans>Check-In Status</Trans>
                            </Group>
                        </Modal.Title>
                        <Modal.CloseButton/>
                    </Modal.Header>
                    <Modal.Body>
                        <LoadingMask/>
                    </Modal.Body>
                </Modal.Content>
            </Modal.Root>
        );
    }

    const checkInLists = checkInListsResponse?.data || [];
    const attendeeCheckIns = attendee.check_ins || [];

    const getCheckInForList = (listId: number | undefined) => {
        return attendeeCheckIns.find(ci => ci.check_in_list_id === listId);
    };

    const isAttendeeEligibleForList = (list: CheckInList) => {
        if (!list.products || list.products.length === 0) {
            return true;
        }
        return list.products.some(product => product.id === attendee.product_id);
    };

    const eligibleLists = checkInLists.filter(list => isAttendeeEligibleForList(list));
    const ineligibleLists = checkInLists.filter(list => !isAttendeeEligibleForList(list));

    const renderListItem = (list: CheckInList, isEligible: boolean) => {
        const checkIn = getCheckInForList(list.id);
        const isCheckedIn = !!checkIn;

        return (
            <Box key={list.id} className={classes.listItem} style={{
                opacity: isEligible ? 1 : 0.6,
                borderColor: isEligible ? 'var(--mantine-color-gray-2)' : 'var(--mantine-color-gray-1)'
            }}>
                <Group justify="space-between" wrap="nowrap">
                    <Group gap="sm" style={{flex: 1}}>
                        {isEligible ? (
                            isCheckedIn ? (
                                <IconCheck size={20} color="var(--mantine-color-green-6)"/>
                            ) : (
                                <IconX size={20} color="var(--mantine-color-gray-5)"/>
                            )
                        ) : (
                            <IconX size={20} color="var(--mantine-color-gray-4)"/>
                        )}
                        <Box style={{flex: 1}}>
                            <Text size="sm" fw={500}>
                                {list.name}
                            </Text>
                            {isCheckedIn && checkIn.created_at && (
                                <Text size="xs" c="dimmed">
                                    {prettyDate(checkIn.created_at, eventTimezone)}
                                </Text>
                            )}
                            {!isEligible && (
                                <Text size="xs" c="dimmed">
                                    <Trans>Attendee's ticket not included in this list</Trans>
                                </Text>
                            )}
                        </Box>
                    </Group>
                    {isEligible ? (
                        <Badge
                            variant="light"
                            color={isCheckedIn ? 'green' : 'gray'}
                            size="sm"
                        >
                            {isCheckedIn ? t`Checked In` : t`Not Checked In`}
                        </Badge>
                    ) : (
                        <Badge
                            variant="light"
                            color="gray"
                            size="sm"
                        >
                            {t`Not Eligible`}
                        </Badge>
                    )}
                </Group>
            </Box>
        );
    };

    return (
        <Modal.Root opened={isOpen} onClose={onClose} size="md">
            <Modal.Overlay/>
            <Modal.Content>
                <Modal.Header>
                    <Modal.Title>
                        <Group gap="xs">
                            <IconQrcode size={20}/>
                            <Trans>Check-In Status</Trans>
                        </Group>
                    </Modal.Title>
                    <Modal.CloseButton/>
                </Modal.Header>
                <Modal.Body>
                    <Stack gap="md">
                        <Box>
                            <Text size="sm" fw={500} mb="xs">
                                <Trans>
                                    {attendee.first_name} {attendee.last_name}
                                </Trans>
                            </Text>
                            <Text size="xs" c="dimmed">
                                {attendee.public_id}
                            </Text>
                        </Box>

                        {checkInLists.length === 0 ? (
                            <Text c="dimmed" ta="center" py="xl">
                                <Trans>No check-in lists available for this event.</Trans>
                            </Text>
                        ) : (
                            <ScrollArea h={Math.min(checkInLists.length * 100, 400)}>
                                <Stack gap="md">
                                    {eligibleLists.length > 0 && (
                                        <Stack gap="xs">
                                            <Text size="xs" fw={600} c="dimmed" tt="uppercase">
                                                <Trans>Eligible Check-In Lists</Trans>
                                            </Text>
                                            {eligibleLists.map((list) => renderListItem(list, true))}
                                        </Stack>
                                    )}

                                    {ineligibleLists.length > 0 && (
                                        <Stack gap="xs">
                                            <Text size="xs" fw={600} c="dimmed" tt="uppercase">
                                                <Trans>Other Lists (Ticket Not Included)</Trans>
                                            </Text>
                                            {ineligibleLists.map((list) => renderListItem(list, false))}
                                        </Stack>
                                    )}
                                </Stack>
                            </ScrollArea>
                        )}
                    </Stack>
                </Modal.Body>
            </Modal.Content>
        </Modal.Root>
    );
};
