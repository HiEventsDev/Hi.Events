import {t} from "@lingui/macro";
import {LineupEntry} from "../../../../api/lineup.client.ts";
import {BILLING_TIERS, initialsFor, profilePhoto} from "../../../../utilites/lineup.ts";
import {responsiveImage} from "../../../../utilites/imageTransform.ts";
import classes from "./Lineup.module.scss";

interface LineupGridProps {
    entries: LineupEntry[];
    onOpen: (entryId: number) => void;
    compact?: boolean;
}

export const LineupGrid = ({entries, onOpen, compact}: LineupGridProps) => {
    const tiers = BILLING_TIERS
        .map(tier => ({tier, entries: entries.filter(entry => entry.billing_tier === tier)}))
        .filter(group => group.entries.length > 0);

    return (
        <div className={classes.lineup} data-density={compact ? 'compact' : undefined}>
            {tiers.map(({tier, entries: tierEntries}) => (
                <div
                    key={tier}
                    className={classes.tier}
                    data-tier={tier.toLowerCase()}
                    data-count={Math.min(tierEntries.length, 4)}
                >
                    {tierEntries.map(entry => (
                        <LineupCard key={entry.id} entry={entry} onOpen={() => onOpen(entry.id)}/>
                    ))}
                </div>
            ))}
        </div>
    );
};

const LineupCard = ({entry, onOpen}: { entry: LineupEntry, onOpen: () => void }) => {
    const photo = profilePhoto(entry.profile);
    const subtitle = entry.role_label || entry.profile.tagline;
    const name = entry.profile.name;

    return (
        <button
            type="button"
            className={classes.card}
            onClick={onOpen}
            aria-label={t`About ${name}`}
            data-testid="event-lineup-card"
        >
            <span
                className={classes.cardMedia}
                style={photo ? {
                    backgroundColor: photo.avg_colour ?? undefined,
                    backgroundImage: photo.lqip_base64 ? `url(${photo.lqip_base64})` : undefined,
                } : undefined}
                data-empty={!photo || undefined}
            >
                {photo
                    ? <img {...responsiveImage(photo.url, '(min-width: 768px) 340px, 50vw')} alt="" loading="lazy" decoding="async"/>
                    : <span className={classes.monogram}>{initialsFor(entry.profile.name)}</span>}
            </span>
            <span className={classes.cardBody}>
                <span className={classes.cardName}>{entry.profile.name}</span>
                {subtitle && <span className={classes.cardSubtitle}>{subtitle}</span>}
            </span>
        </button>
    );
};
