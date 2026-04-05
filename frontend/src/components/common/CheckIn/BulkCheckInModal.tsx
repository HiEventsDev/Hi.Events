import {Button, Modal, Stack, Text} from "@mantine/core";
import {IconUsers, IconTicket} from "@tabler/icons-react";
import {t, Trans} from "@lingui/macro";
import {Attendee} from "../../../types.ts";

interface BulkCheckInModalProps {
    isOpen: boolean;
    attendees: Attendee[];
    products: { id: number; title: string; }[] | undefined;
    isPending: boolean;
    onClose: () => void;
    onConfirm: () => void;
}

export const BulkCheckInModal = ({
    isOpen,
    attendees,
    products,
    isPending,
    onClose,
    onConfirm,
}: BulkCheckInModalProps) => {
    if (attendees.length === 0) return null;

    return (
        <Modal
            opened={isOpen}
            onClose={onClose}
            title={t`Check in remaining order attendees?`}
            size="md"
        >
            <Stack>
                <Text size="sm">
                    <Trans>
                        This order has {attendees.length} other attendee(s) who have not been checked in yet. Would you like to check them all in?
                    </Trans>
                </Text>
                {attendees.map(attendee => (
                    <div key={attendee.public_id} style={{display: 'flex', alignItems: 'center', gap: 8, padding: '4px 0'}}>
                        <IconUsers size={16} color="#555"/>
                        <div>
                            <Text size="sm" fw={500}>{attendee.first_name} {attendee.last_name}</Text>
                            <Text size="xs" c="dimmed" style={{display: 'flex', alignItems: 'center', gap: 4}}>
                                <IconTicket size={12}/>
                                {products?.find(p => p.id === attendee.product_id)?.title}
                            </Text>
                        </div>
                    </div>
                ))}
                <Button
                    leftSection={<IconUsers size={20}/>}
                    onClick={onConfirm}
                    loading={isPending}
                    fullWidth
                >
                    <Trans>Check in all {attendees.length} attendee(s)</Trans>
                </Button>
                <Button
                    onClick={onClose}
                    variant="light"
                    fullWidth
                >
                    {t`Skip`}
                </Button>
            </Stack>
        </Modal>
    );
};
