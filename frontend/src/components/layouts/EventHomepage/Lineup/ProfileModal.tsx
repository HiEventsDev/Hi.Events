import {useState} from "react";
import {t} from "@lingui/macro";
import {Modal} from "@mantine/core";
import {embeddedDialogStyles, useEmbeddedDialogPlacement} from "../../../widget/EmbeddedDialogOffset.tsx";
import {IconArrowLeft, IconX} from "@tabler/icons-react";
import {Event} from "../../../../types.ts";
import {EventLineup, LineupEntry} from "../../../../api/lineup.client.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {initialsFor} from "../../../../utilites/lineup.ts";
import {UserGeneratedContent} from "../../../common/UserGeneratedContent";
import {ProfileLinkIcon, profileLinkLabel} from "../../../common/ProfileLinkIcon";
import {findEmbeddableLink, ProfileEmbed} from "./ProfileEmbed.tsx";
import classes from "./Lineup.module.scss";

interface ProfileModalProps {
    event: Event;
    entry: LineupEntry;
    lineup: EventLineup;
    portalTarget?: HTMLElement;
    onClose: () => void;
    onBack?: () => void;
    onOpenItem: (itemId: number) => void;
}

export const ProfileModal = ({event, entry, lineup, portalTarget, onClose, onBack, onOpenItem}: ProfileModalProps) => {
    const embeddedPlacement = useEmbeddedDialogPlacement();
    const {profile} = entry;
    const [photoIndex, setPhotoIndex] = useState(0);
    const photo = profile.images[photoIndex];
    const embeddable = findEmbeddableLink(profile.links);
    const spacesById = new Map(lineup.spaces.map(space => [space.id, space]));
    const appearances = lineup.schedule_items.filter(item => item.lineup_entry_ids.includes(entry.id));

    return (
        <Modal
            opened
            onClose={onClose}
            withCloseButton={false}
            centered={!embeddedPlacement}
            yOffset={embeddedPlacement?.yOffset}
            styles={embeddedDialogStyles(embeddedPlacement)}
            size={640}
            padding={0}
            radius={24}
            portalProps={{target: portalTarget}}
            overlayProps={{backgroundOpacity: 0.6, blur: 6}}
            title={profile.name}
            classNames={{content: classes.profileModal, body: classes.profileModalBody, header: classes.visuallyHidden}}
        >
            <div className={classes.dialogControls}>
                {onBack && (
                    <button type="button" className={classes.dialogBack} onClick={onBack} aria-label={t`Back`}>
                        <IconArrowLeft size={18}/>
                    </button>
                )}
                <button type="button" className={classes.profileClose} onClick={onClose} aria-label={t`Close`}>
                    <IconX size={18}/>
                </button>
            </div>

            <div className={classes.profileHero} data-empty={!photo || undefined} style={photo?.avg_colour ? {backgroundColor: photo.avg_colour} : undefined}>
                {photo
                    ? <img src={photo.url} alt={profile.name}/>
                    : <span className={classes.monogram}>{initialsFor(profile.name)}</span>}
                {profile.images.length > 1 && (
                    <div className={classes.photoDots}>
                        {profile.images.map((image, index) => {
                            const photoNumber = index + 1;
                            return (
                                <button
                                    key={image.id}
                                    type="button"
                                    data-active={index === photoIndex || undefined}
                                    aria-current={index === photoIndex || undefined}
                                    aria-label={t`Photo ${photoNumber}`}
                                    onClick={() => setPhotoIndex(index)}
                                />
                            );
                        })}
                    </div>
                )}
            </div>

            <div className={classes.profileContent}>
                {entry.role_label && <div className={classes.profileRole}>{entry.role_label}</div>}
                <h3 className={classes.profileName}>{profile.name}</h3>
                {profile.tagline && <p className={classes.profileTagline}>{profile.tagline}</p>}

                {profile.links.length > 0 && (
                    <div className={classes.profileLinks}>
                        {profile.links.map(link => (
                            <a
                                key={link.url}
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                className={classes.profileLink}
                                aria-label={profileLinkLabel(link.type)}
                                title={profileLinkLabel(link.type)}
                            >
                                <ProfileLinkIcon type={link.type}/>
                            </a>
                        ))}
                    </div>
                )}

                {appearances.length > 0 && (
                    <ul className={classes.appearances}>
                        {appearances.map(item => {
                            const space = item.event_space_id ? spacesById.get(item.event_space_id) : undefined;
                            return (
                                <li key={item.id}>
                                    <button type="button" className={classes.appearanceButton} onClick={() => onOpenItem(item.id)}>
                                        <span className={classes.appearanceDay}>
                                            {formatDateWithLocale(item.starts_at, 'weekdayDate', event.timezone)}
                                        </span>
                                        <span>
                                            {formatDateWithLocale(item.starts_at, 'timeOnly', event.timezone)} – {formatDateWithLocale(item.ends_at, 'timeOnly', event.timezone)}
                                        </span>
                                        {space && <span className={classes.appearanceSpace}>{space.name}</span>}
                                        {item.title && <span className={classes.appearanceTitle}>{item.title}</span>}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {embeddable && <ProfileEmbed link={embeddable}/>}

                {profile.bio && <UserGeneratedContent className={classes.profileBio} html={profile.bio}/>}
            </div>
        </Modal>
    );
};
