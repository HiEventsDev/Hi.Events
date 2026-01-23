import {Button, CopyButton, Group, Stack, Text, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {GenericModalProps} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {IconCheck, IconCopy, IconExternalLink} from "@tabler/icons-react";

interface CheckInListSuccessModalProps {
    checkInListName: string;
    checkInListShortId: string;
}

export const CheckInListSuccessModal = ({
                                            onClose,
                                            checkInListName,
                                            checkInListShortId,
                                        }: GenericModalProps & CheckInListSuccessModalProps) => {
    const checkInUrl = `${window.location.origin}/check-in/${checkInListShortId}`;

    return (
        <Modal opened onClose={onClose} heading={t`Check-In List Created` + ' 🎉'} size="sm">
            <Stack gap="md">
                <Text size="sm">
                    {t`Your check-in list has been created successfully. Share the link below with your check-in staff.`}
                </Text>

                <div>
                    <Text size="sm" fw={500} mb={4}>
                        {checkInListName}
                    </Text>
                    <TextInput
                        value={checkInUrl}
                        readOnly
                        rightSection={
                            <CopyButton value={checkInUrl}>
                                {({copied, copy}) => (
                                    <Button
                                        size="compact-sm"
                                        variant="subtle"
                                        onClick={copy}
                                        leftSection={copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                                    >
                                        {copied ? t`Copied` : t`Copy`}
                                    </Button>
                                )}
                            </CopyButton>
                        }
                    />
                </div>

                <Group grow>
                    <Button
                        variant="light"
                        leftSection={<IconExternalLink size={16}/>}
                        onClick={() => window.open(checkInUrl, '_blank')}
                    >
                        {t`Open Check-In Page`}
                    </Button>
                    <Button onClick={onClose}>
                        {t`Done`}
                    </Button>
                </Group>
            </Stack>
        </Modal>
    );
};
