import {t} from "@lingui/macro";
import {IconMail, IconMapPin, IconWorld} from "@tabler/icons-react";
import {Organizer} from "../../../types.ts";
import {getGoogleMapsUrl, getShortLocationDisplay} from "../../../utilites/addressUtilities.ts";
import {organizerHomepageUrl} from "../../../utilites/urlHelper.ts";
import {organizerSocialLinks, websiteHostname} from "../../../utilites/organizerLinks.ts";
import {UserGeneratedContent} from "../../common/UserGeneratedContent";
import {sizedImageUrl} from "../../../utilites/imageTransform.ts";
import classes from "./EventHomepage.module.scss";

interface OrganizerSectionProps {
    heading: string;
    organizer: Organizer;
    logo?: string;
    onContact: () => void;
}

export const OrganizerSection = ({heading, organizer, logo, onContact}: OrganizerSectionProps) => {
    const address = organizer.location?.structured_address;
    const shortAddress = getShortLocationDisplay(address);
    const socialLinks = organizerSocialLinks(organizer);
    const website = organizer.website;
    const hostname = website ? websiteHostname(website) : null;

    return (
        <section className={classes.section} id="organizer">
            <div className={classes.sectionHeader}>
                <h2 className={classes.sectionTitle}>{heading}</h2>
            </div>
            <div className={classes.hostCard}>
                <div className={classes.hostCardTop}>
                    {logo
                        ? <img src={sizedImageUrl(logo, 160)} alt="" className={classes.hostCardAvatar}/>
                        : <span className={classes.hostCardAvatar} data-placeholder="" aria-hidden="true">{organizer.name.charAt(0).toUpperCase()}</span>}
                    <div className={classes.hostCardIdentity}>
                        <h3 className={classes.hostCardName}>
                            <a href={organizerHomepageUrl(organizer)}>{organizer.name}</a>
                        </h3>
                        {(shortAddress || hostname) && (
                            <div className={classes.hostCardMeta}>
                                {shortAddress && address && (
                                    <a href={getGoogleMapsUrl(address)} target="_blank" rel="nofollow ugc noopener noreferrer">
                                        <IconMapPin size={14}/>
                                        {shortAddress}
                                    </a>
                                )}
                                {website && hostname && (
                                    <a href={website} target="_blank" rel="nofollow ugc noopener noreferrer">
                                        <IconWorld size={14}/>
                                        {hostname}
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                    <button type="button" onClick={onContact} className={classes.pillButton}>
                        <IconMail/>
                        {t`Contact`}
                    </button>
                </div>

                {organizer.description && (
                    <UserGeneratedContent className={classes.hostCardBio} html={organizer.description}/>
                )}

                {socialLinks.length > 0 && (
                    <div className={classes.socialRow}>
                        {socialLinks.map(({platform, url, label, Icon}) => (
                            <a
                                key={platform}
                                href={url}
                                target="_blank"
                                rel="nofollow ugc noopener noreferrer"
                                className={classes.socialButton}
                                title={label}
                                aria-label={label}
                            >
                                <Icon size={18}/>
                            </a>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
};
