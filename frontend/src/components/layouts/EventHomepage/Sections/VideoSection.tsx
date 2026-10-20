import {useEffect, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {IconPlayerPlayFilled} from "@tabler/icons-react";
import {HomepageSection} from "../../../../types.ts";
import {HomepageSectionClasses} from "../Lineup";
import {SectionShell} from "./SectionShell.tsx";
import classes from "./Sections.module.scss";

export const videoEmbedUrl = (url?: string | null): { src: string, provider: string } | null => {
    if (!url) {
        return null;
    }

    try {
        const parsed = new URL(url);
        const host = parsed.hostname.replace(/^(www|m)\./, '');

        if (host === 'youtu.be' || host === 'youtube.com') {
            const id = host === 'youtu.be'
                ? parsed.pathname.slice(1)
                : parsed.searchParams.get('v') ?? parsed.pathname.match(/^\/(?:embed|shorts|live)\/([\w-]+)/)?.[1];
            return id && /^[\w-]{6,20}$/.test(id)
                // eslint-disable-next-line lingui/no-unlocalized-strings
                ? {src: `https://www.youtube-nocookie.com/embed/${id}?autoplay=1`, provider: 'YouTube'}
                : null;
        }

        if (host === 'vimeo.com' || host === 'player.vimeo.com') {
            const id = parsed.pathname.match(/(\d{5,})/)?.[1];
            // eslint-disable-next-line lingui/no-unlocalized-strings
            return id ? {src: `https://player.vimeo.com/video/${id}?autoplay=1`, provider: 'Vimeo'} : null;
        }
    } catch {
        return null;
    }

    return null;
};

export const VideoSection = ({section, sectionClasses}: { section: HomepageSection, sectionClasses: HomepageSectionClasses }) => {
    const [playing, setPlaying] = useState(false);
    const playerRef = useRef<HTMLIFrameElement>(null);
    const embed = videoEmbedUrl(section.video_url);

    useEffect(() => {
        if (playing) {
            playerRef.current?.focus({preventScroll: true});
        }
    }, [playing]);

    if (!embed) {
        return null;
    }

    const provider = embed.provider;

    return (
        <SectionShell section={section} sectionClasses={sectionClasses}>
            <div className={classes.video}>
                {playing
                    ? (
                        <iframe
                            ref={playerRef}
                            src={embed.src}
                            title={section.heading || provider}
                            allow="autoplay; encrypted-media; fullscreen; picture-in-picture"
                            allowFullScreen
                        />
                    )
                    : (
                        <button type="button" className={classes.videoPlaceholder} onClick={() => setPlaying(true)}>
                            <span className={classes.videoPlay}><IconPlayerPlayFilled size={26}/></span>
                            <span className={classes.videoNote}>{t`Plays from ${provider}`}</span>
                        </button>
                    )}
            </div>
        </SectionShell>
    );
};
