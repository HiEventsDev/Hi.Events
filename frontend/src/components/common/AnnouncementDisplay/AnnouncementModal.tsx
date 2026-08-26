import {Button, Modal} from "@mantine/core";
import {t} from "@lingui/macro";
import {Announcement} from "../../../api/announcement.client";
import {BouncingEmoji} from "../BouncingEmoji";
import classes from "./AnnouncementDisplay.module.scss";

interface AnnouncementModalProps {
    announcement: Announcement;
    onDismiss: () => void;
    onClose: () => void;
}

export const AnnouncementModal = ({announcement, onDismiss, onClose}: AnnouncementModalProps) => (
    <Modal
        opened
        onClose={onClose}
        centered
        size="md"
        withCloseButton={false}
        className={classes.modal}
    >
        <div className={classes.modalContent} data-testid="announcement-modal">
            <div className={classes.modalEmoji}>
                <BouncingEmoji emoji={announcement.emoji || '📣'} size={64}/>
            </div>

            <div className={classes.modalTitle}>{announcement.title}</div>

            <div
                className={classes.modalBody}
                dangerouslySetInnerHTML={{__html: announcement.content}}
            />

            <div className={classes.modalActions}>
                {announcement.cta_label && announcement.cta_url && (
                    <Button
                        component="a"
                        href={announcement.cta_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        size="md"
                        data-testid="announcement-modal-cta"
                    >
                        {announcement.cta_label}
                    </Button>
                )}
                <Button
                    variant="light"
                    size="md"
                    onClick={onDismiss}
                    data-testid="announcement-modal-dismiss"
                >
                    {t`Got it`}
                </Button>
            </div>
        </div>
    </Modal>
);
