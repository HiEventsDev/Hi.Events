import {useState} from "react";
import {t} from "@lingui/macro";
import {IconPlayerPlayFilled} from "@tabler/icons-react";
import {ProfileLink} from "../../../../api/lineup.client.ts";
import {profileLinkLabel} from "../../../common/ProfileLinkIcon";
import classes from "./Lineup.module.scss";

interface Embed {
    src: string;
    height: number;
    title: string;
}

const embedFor = (link: ProfileLink): Embed | null => {
    try {
        const url = new URL(link.url);

        if (link.type === 'SPOTIFY') {
            const match = url.pathname.match(/\/(artist|album|track|playlist|show|episode)\/([A-Za-z0-9]+)/);
            return match
                ? {src: `https://open.spotify.com/embed/${match[1]}/${match[2]}`, height: 152, title: profileLinkLabel(link.type)}
                : null;
        }

        if (link.type === 'YOUTUBE') {
            const videoId = url.hostname.endsWith('youtu.be')
                ? url.pathname.slice(1)
                : url.searchParams.get('v');
            return videoId && /^[\w-]{6,20}$/.test(videoId)
                ? {src: `https://www.youtube-nocookie.com/embed/${videoId}`, height: 0, title: profileLinkLabel(link.type)}
                : null;
        }

        if (link.type === 'SOUNDCLOUD') {
            return {
                src: `https://w.soundcloud.com/player/?url=${encodeURIComponent(link.url)}&visual=false&show_comments=false`,
                height: 166,
                title: profileLinkLabel(link.type),
            };
        }
    } catch {
        return null;
    }

    return null;
};

export const findEmbeddableLink = (links: ProfileLink[]): ProfileLink | undefined =>
    links.find(link => embedFor(link) !== null);

export const ProfileEmbed = ({link}: { link: ProfileLink }) => {
    const [loaded, setLoaded] = useState(false);
    const embed = embedFor(link);

    if (!embed) {
        return null;
    }

    const service = embed.title;

    if (!loaded) {
        return (
            <button type="button" className={classes.embedPlaceholder} onClick={() => setLoaded(true)}>
                <span className={classes.embedPlay}><IconPlayerPlayFilled size={16}/></span>
                <span>
                    <strong>{t`Play on ${service}`}</strong>
                    <small>{t`Loads content from ${service}`}</small>
                </span>
            </button>
        );
    }

    return (
        <iframe
            className={classes.embedFrame}
            data-video={embed.height === 0 || undefined}
            src={embed.src}
            title={embed.title}
            height={embed.height || undefined}
            loading="lazy"
            allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
        />
    );
};
