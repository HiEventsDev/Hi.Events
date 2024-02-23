import {useDisclosure} from '@mantine/hooks';
import {Button, Dialog, Group, Text} from '@mantine/core';

interface ConfirmationDiaglogProps {
    message: string;
    onConfirm: () => void;
    buttonLabel: string;
}


export const confirm = () => {

}

export const ConfirmationDialog = ({message, onConfirm, buttonLabel}: ConfirmationDiaglogProps) => {
    const [opened, {close}] = useDisclosure(true);

    return (
        <>
            <Dialog opened={opened} withCloseButton onClose={close} size="lg" radius="md">
                <Text size="sm" mb="xs" fw={500}>
                    {message}
                </Text>

                <Group align="flex-end">
                    <Button onClick={() => {
                        onConfirm();
                        close();
                    }}>{buttonLabel}</Button>
                </Group>
            </Dialog>
        </>
    );
}