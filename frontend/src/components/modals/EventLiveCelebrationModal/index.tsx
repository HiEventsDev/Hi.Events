import {Button, CopyButton, Modal, Text, TextInput, Tooltip} from '@mantine/core';
import {useDisclosure} from '@mantine/hooks';
import {
    IconCheck,
    IconCode,
    IconCopy,
    IconExternalLink,
    IconShare
} from '@tabler/icons-react';
import {t} from "@lingui/macro";
import {NavLink} from "react-router";
import {ShareModal} from "../ShareModal";
import classes from './EventLiveCelebrationModal.module.scss';

interface EventLiveCelebrationModalProps {
    opened: boolean;
    onClose: () => void;
    url: string;
    eventTitle: string;
    eventId: string;
}

export const EventLiveCelebrationModal = ({
    opened,
    onClose,
    url,
    eventTitle,
    eventId
}: EventLiveCelebrationModalProps) => {
    const [shareModalOpened, {open: openShareModal, close: closeShareModal}] = useDisclosure(false);

    return (
        <>
            <Modal
                opened={opened}
                onClose={onClose}
                centered
                size="md"
                withCloseButton={false}
                className={classes.modal}
            >
                <div className={classes.content}>
                    <div className={classes.celebrationEmoji}>
                        🎉
                    </div>

                    <Text className={classes.title}>
                        {t`Your event is live!`}
                    </Text>

                    <Text className={classes.subtitle}>
                        {t`Congratulations! Your event is now visible to the public.`}
                    </Text>

                    <div className={classes.urlSection}>
                        <TextInput
                            value={url}
                            readOnly
                            size="md"
                            classNames={{
                                input: classes.urlInput
                            }}
                            rightSectionWidth={100}
                            rightSection={
                                <CopyButton value={url} timeout={2000}>
                                    {({copied, copy}) => (
                                        <Tooltip label={copied ? t`Copied!` : t`Copy link`}>
                                            <Button
                                                variant="light"
                                                color={copied ? 'teal' : 'gray'}
                                                onClick={copy}
                                                size="sm"
                                                leftSection={copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                                            >
                                                {copied ? t`Copied` : t`Copy`}
                                            </Button>
                                        </Tooltip>
                                    )}
                                </CopyButton>
                            }
                        />
                    </div>

                    <div className={classes.actions}>
                        <Button
                            component="a"
                            href={url}
                            target="_blank"
                            variant="light"
                            leftSection={<IconExternalLink size={16}/>}
                            className={classes.actionButton}
                        >
                            {t`View Event Page`}
                        </Button>

                        <Button
                            variant="light"
                            leftSection={<IconShare size={16}/>}
                            onClick={openShareModal}
                            className={classes.actionButton}
                        >
                            {t`Share Event`}
                        </Button>

                        <Button
                            component={NavLink}
                            to={`/manage/event/${eventId}/widget`}
                            variant="light"
                            leftSection={<IconCode size={16}/>}
                            onClick={onClose}
                            className={classes.actionButton}
                        >
                            {t`Embed Widget`}
                        </Button>
                    </div>

                    <Button
                        onClick={onClose}
                        variant="filled"
                        size="md"
                        className={classes.doneButton}
                    >
                        {t`Done`}
                    </Button>
                </div>
            </Modal>

            <ShareModal
                url={url}
                title={eventTitle}
                modalTitle={t`Share Event`}
                opened={shareModalOpened}
                onClose={closeShareModal}
            />
        </>
    );
};
