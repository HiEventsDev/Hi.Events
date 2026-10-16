import {
    IconBrandApple,
    IconBrandBandcamp,
    IconBrandFacebook,
    IconBrandGithub,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandSoundcloud,
    IconBrandSpotify,
    IconBrandTiktok,
    IconBrandX,
    IconBrandYoutube,
    IconWorld,
    TablerIcon,
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {ProfileLinkType} from "../../../api/lineup.client.ts";

const ICONS: Record<ProfileLinkType, TablerIcon> = {
    SPOTIFY: IconBrandSpotify,
    APPLE_MUSIC: IconBrandApple,
    SOUNDCLOUD: IconBrandSoundcloud,
    BANDCAMP: IconBrandBandcamp,
    YOUTUBE: IconBrandYoutube,
    INSTAGRAM: IconBrandInstagram,
    TIKTOK: IconBrandTiktok,
    X: IconBrandX,
    FACEBOOK: IconBrandFacebook,
    LINKEDIN: IconBrandLinkedin,
    GITHUB: IconBrandGithub,
    WEBSITE: IconWorld,
};

export const profileLinkLabel = (type: ProfileLinkType): string => {
    switch (type) {
        /* eslint-disable lingui/no-unlocalized-strings */
        case 'SPOTIFY':
            return 'Spotify';
        case 'APPLE_MUSIC':
            return 'Apple Music';
        case 'SOUNDCLOUD':
            return 'SoundCloud';
        case 'BANDCAMP':
            return 'Bandcamp';
        case 'YOUTUBE':
            return 'YouTube';
        case 'INSTAGRAM':
            return 'Instagram';
        case 'TIKTOK':
            return 'TikTok';
        case 'X':
            return 'X';
        case 'FACEBOOK':
            return 'Facebook';
        case 'LINKEDIN':
            return 'LinkedIn';
        case 'GITHUB':
            return 'GitHub';
        /* eslint-enable lingui/no-unlocalized-strings */
        default:
            return t`Website`;
    }
};

export const ProfileLinkIcon = ({type, size = 18}: { type: ProfileLinkType, size?: number }) => {
    const Icon = ICONS[type] ?? IconWorld;

    return <Icon size={size} stroke={1.75}/>;
};
