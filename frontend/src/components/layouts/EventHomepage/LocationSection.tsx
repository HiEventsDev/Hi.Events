import {t} from "@lingui/macro";
import {IconArrowUpRight, IconMapPin} from "@tabler/icons-react";
import {EventLocationDisplay} from "../../../utilites/effectiveLocation.ts";
import classes from "./EventHomepage.module.scss";

interface LocationSectionProps {
    heading: string;
    location: EventLocationDisplay;
}

export const LocationSection = ({heading, location}: LocationSectionProps) => (
    <section className={classes.section} id="location">
        <div className={classes.sectionHeader}>
            <h2 className={classes.sectionTitle}>{heading}</h2>
        </div>
        <div className={classes.locationCard}>
            <span className={classes.factTile} aria-hidden="true"><IconMapPin/></span>
            <div className={classes.locationText}>
                {location.venueName && <div className={classes.locationName}>{location.venueName}</div>}
                {location.full && <div className={classes.locationAddress}>{location.full}</div>}
                {location.mapsUrl && (
                    <a href={location.mapsUrl} target="_blank" rel="nofollow ugc noopener noreferrer" className={classes.factLink}>
                        {t`Get directions`}
                        <IconArrowUpRight size={14}/>
                    </a>
                )}
            </div>
        </div>
    </section>
);
