import {ActionIcon, Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSpeakerphone, IconX} from "@tabler/icons-react";
import {Announcement} from "../../../api/announcement.client";
import classes from "./AnnouncementDisplay.module.scss";

interface AnnouncementBannerProps {
    announcement: Announcement;
    onDismiss: () => void;
}

export const AnnouncementBanner = ({announcement, onDismiss}: AnnouncementBannerProps) => (
    <div className={classes.banner} role="status" data-testid="announcement-banner">
        <IconSpeakerphone size={22} className={classes.bannerIcon}/>
        <div className={classes.bannerText}>
            <span className={classes.bannerTitle}>{announcement.title}</span>
            <span className={classes.bannerContent}>{announcement.content}</span>
        </div>
        <div className={classes.bannerActions}>
            {announcement.cta_label && announcement.cta_url && (
                <Button
                    component="a"
                    href={announcement.cta_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    size="compact-sm"
                    variant="white"
                    data-testid="announcement-banner-cta"
                >
                    {announcement.cta_label}
                </Button>
            )}
            <ActionIcon
                variant="subtle"
                color="gray"
                onClick={onDismiss}
                aria-label={t`Dismiss announcement`}
                data-testid="announcement-banner-dismiss"
            >
                <IconX size={18}/>
            </ActionIcon>
        </div>
    </div>
);
