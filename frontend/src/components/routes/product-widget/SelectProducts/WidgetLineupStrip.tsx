import {useMemo} from "react";
import {t} from "@lingui/macro";
import {IconArrowUpRight} from "@tabler/icons-react";
import {Event, EventOccurrence} from "../../../../types.ts";
import {LineupEntry} from "../../../../api/lineup.client.ts";
import {useGetEventLineupPublic} from "../../../../queries/useGetEventLineupPublic.ts";
import {getLineupTerminology, initialsFor, profilePhoto} from "../../../../utilites/lineup.ts";
import {lineupForOccurrence} from "../../../../utilites/lineupSchedule.ts";
import {eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import {resolveHomepageSections} from "../../../../utilites/homepageSections.ts";

const MAX_PEOPLE = 8;

const highlightedEntries = (entries: LineupEntry[]): LineupEntry[] => {
    const featured = entries.filter(entry => entry.billing_tier !== 'STANDARD');
    return featured.length > 0 ? featured : entries;
};

export const WidgetLineupStrip = ({event, occurrence}: { event: Event, occurrence?: EventOccurrence }) => {
    const lineupEnabled = resolveHomepageSections(event.settings?.homepage_sections)
        .some(section => section.type === 'LINEUP' && section.enabled);
    const {data: fullLineup} = useGetEventLineupPublic(lineupEnabled ? event.id : undefined);
    const entries = useMemo(() => {
        if (!fullLineup) {
            return [];
        }
        const lineup = occurrence ? lineupForOccurrence(fullLineup, occurrence, event.timezone) : fullLineup;
        return highlightedEntries(lineup.entries);
    }, [fullLineup, occurrence, event.timezone]);

    if (entries.length === 0) {
        return null;
    }

    const shown = entries.slice(0, MAX_PEOPLE);
    const remaining = entries.length - shown.length;

    return (
        <div className="hi-lineup-strip" data-testid="widget-lineup-strip">
            <div className="hi-lineup-strip-header">
                <span className="hi-lineup-strip-title">{getLineupTerminology(event.category).lineup}</span>
                <a className="hi-lineup-strip-link" href={`${eventHomepageUrl(event)}#lineup`} target="_blank" rel="noopener">
                    {remaining > 0 ? t`+${remaining} more` : t`See all`}
                    <IconArrowUpRight size={14}/>
                </a>
            </div>
            <ul className="hi-lineup-strip-people">
                {shown.map(entry => {
                    const photo = profilePhoto(entry.profile);
                    return (
                        <li key={entry.id} className="hi-lineup-strip-person">
                            <span className="hi-lineup-strip-avatar" style={photo?.avg_colour ? {backgroundColor: photo.avg_colour} : undefined}>
                                {photo ? <img src={photo.url} alt="" loading="lazy"/> : initialsFor(entry.profile.name)}
                            </span>
                            <span className="hi-lineup-strip-name">{entry.profile.name}</span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
};
