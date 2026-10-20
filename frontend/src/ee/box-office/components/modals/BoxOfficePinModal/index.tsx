import {Button, CopyButton, Group, Stack, Text, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconCheck, IconCopy, IconExternalLink} from "@tabler/icons-react";
import {GenericModalProps} from "../../../../../types.ts";
import {Modal} from "../../../../../components/common/Modal";
import {Callout} from "../../../../../components/common/Callout";
import {boxOfficeUrl} from "../../common/BoxOfficeTable";
import classes from "./BoxOfficePinModal.module.scss";

export type BoxOfficePinMode = 'created' | 'set' | 'reset';

interface BoxOfficePinModalProps {
    boxOfficeName: string;
    boxOfficeShortId: string;
    pin: string;
    mode: BoxOfficePinMode;
}

const headings: Record<BoxOfficePinMode, () => string> = {
    created: () => t`Box Office Created` + ' 🎉',
    set: () => t`Box Office PIN`,
    reset: () => t`New PIN`,
};

const intros: Record<BoxOfficePinMode, () => string> = {
    created: () => t`Share the link and PIN below with your door staff.`,
    set: () => t`Staff need a PIN to sign in, so we generated one. Share the link and PIN below with your door staff.`,
    reset: () => t`Share the new PIN with your door staff. Anyone who was signed in has been signed out.`,
};

export const BoxOfficePinModal = ({onClose, boxOfficeName, boxOfficeShortId, pin, mode}: GenericModalProps & BoxOfficePinModalProps) => {
    const url = boxOfficeUrl(boxOfficeShortId);

    return (
        <Modal opened onClose={onClose} heading={headings[mode]()} size="sm">
            <Stack gap="md">
                <Text size="sm">
                    {intros[mode]()}
                </Text>

                <div>
                    <Text size="sm" fw={500} mb={4}>{boxOfficeName}</Text>
                    <TextInput
                        value={url}
                        readOnly
                        rightSection={
                            <CopyButton value={url}>
                                {({copied, copy}) => (
                                    <Button size="compact-sm" variant="subtle" onClick={copy}
                                            leftSection={copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}>
                                        {copied ? t`Copied` : t`Copy`}
                                    </Button>
                                )}
                            </CopyButton>
                        }
                    />
                </div>

                <div className={classes.pinBlock}>
                    <div className={classes.pinLabel}>{t`PIN`}</div>
                    <div className={classes.pinValue} data-testid="box-office-pin-value">{pin}</div>
                    <CopyButton value={pin}>
                        {({copied, copy}) => (
                            <Button size="xs" variant="light" onClick={copy}
                                    leftSection={copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}>
                                {copied ? t`Copied` : t`Copy PIN`}
                            </Button>
                        )}
                    </CopyButton>
                </div>

                <Callout variant="warning">
                    {t`Anyone with this link and PIN can sell tickets for this event, like a shared document link. The PIN won't be shown again, but you can reset it any time from the box office menu.`}
                </Callout>

                <Group grow>
                    <Button variant="light" leftSection={<IconExternalLink size={16}/>} onClick={() => window.open(url, '_blank')}>
                        {t`Open Box Office`}
                    </Button>
                    <Button onClick={onClose} data-testid="box-office-pin-done-button">
                        {t`Done`}
                    </Button>
                </Group>
            </Stack>
        </Modal>
    );
};
