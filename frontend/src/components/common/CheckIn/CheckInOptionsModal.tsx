import {Alert, Button, Modal, Stack} from "@mantine/core";
import {IconAlertCircle, IconCreditCard, IconUserCheck} from "@tabler/icons-react";
import {t, Trans} from "@lingui/macro";
import {Attendee} from "../../../types.ts";

interface CheckInOptionsModalProps {
    isOpen: boolean;
    attendee: Attendee | null;
    isPending: boolean;
    onClose: () => void;
    onCheckIn: (action: 'check-in' | 'check-in-and-mark-order-as-paid') => void;
}

export const CheckInOptionsModal = ({
    isOpen,
    attendee,
    isPending,
    onClose,
    onCheckIn
}: CheckInOptionsModalProps) => {
    if (!attendee) return null;

    return (
        <Modal
            opened={isOpen}
            onClose={onClose}
            title={<Trans>Check in {attendee.first_name} {attendee.last_name}</Trans>}
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
                    onClick={() => onCheckIn('check-in')}
                    loading={isPending}
                    fullWidth
                >
                    {t`Check in only`}
                </Button>
                <Button
                    leftSection={<IconCreditCard size={20}/>}
                    onClick={() => onCheckIn('check-in-and-mark-order-as-paid')}
                    loading={isPending}
                    variant="filled"
                    fullWidth
                >
                    {t`Check in and mark order as paid`}
                </Button>
                <Button
                    onClick={onClose}
                    variant="light"
                    fullWidth
                >
                    {t`Cancel`}
                </Button>
            </Stack>
        </Modal>
    );
};